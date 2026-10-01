<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Correlation;

use App\SharedKernel\Infrastructure\Correlation\CorrelationContext;
use App\SharedKernel\Infrastructure\Correlation\CorrelationHttpListener;
use App\SharedKernel\Infrastructure\Correlation\CorrelationMiddleware;
use App\SharedKernel\Infrastructure\Correlation\CorrelationStamp;
use App\Tests\Support\Fake\SequentialIdGenerator;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;
use App\Tests\Support\Fixtures\Messaging\RecordingCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[CoversClass(CorrelationContext::class)]
#[CoversClass(CorrelationStamp::class)]
#[CoversClass(CorrelationMiddleware::class)]
#[CoversClass(CorrelationHttpListener::class)]
final class CorrelationTest extends TestCase
{
    private const string GENERATED = '01900000-0000-7000-8000-000000000001';

    public function testContextStartsEmptyAndCanBeStartedAndReset(): void
    {
        $context = new CorrelationContext();
        self::assertNull($context->correlationId());
        self::assertNull($context->causationId());

        $context->start('corr', 'cause');
        self::assertSame('corr', $context->correlationId());
        self::assertSame('cause', $context->causationId());

        $context->reset();
        self::assertNull($context->correlationId());
        self::assertNull($context->causationId());
    }

    public function testStampHoldsBothIds(): void
    {
        $stamp = new CorrelationStamp('corr', 'cause');

        self::assertSame('corr', $stamp->correlationId);
        self::assertSame('cause', $stamp->causationId);
        $withoutCause = new CorrelationStamp('corr');
        self::assertNull($withoutCause->causationId);
    }

    public function testOutgoingMessageGetsTheCurrentContextAsStamp(): void
    {
        $context = new CorrelationContext();
        $context->start('corr', 'cause');
        $seen = null;

        $this->middleware($context)->handle(new Envelope(new RecordingCommand('x')), $this->capturing($seen));

        self::assertInstanceOf(Envelope::class, $seen);
        $stamp = $seen->last(CorrelationStamp::class);
        self::assertNotNull($stamp);
        self::assertSame('corr', $stamp->correlationId);
        self::assertSame('cause', $stamp->causationId);
    }

    public function testOutgoingMessageWithoutContextGetsAGeneratedCorrelationId(): void
    {
        $context = new CorrelationContext();
        $seen = null;

        $this->middleware($context)->handle(new Envelope(new RecordingCommand('x')), $this->capturing($seen));

        self::assertInstanceOf(Envelope::class, $seen);
        self::assertSame(self::GENERATED, $seen->last(CorrelationStamp::class)?->correlationId);
        self::assertSame(self::GENERATED, $context->correlationId(), 'the generated id becomes the context');
    }

    public function testAnExistingStampIsNeverReplaced(): void
    {
        $context = new CorrelationContext();
        $context->start('other');
        $seen = null;
        $envelope = new Envelope(new RecordingCommand('x'), [new CorrelationStamp('original', 'cause')]);

        $this->middleware($context)->handle($envelope, $this->capturing($seen));

        self::assertInstanceOf(Envelope::class, $seen);
        self::assertSame('original', $seen->last(CorrelationStamp::class)?->correlationId);
    }

    public function testReceivedEventRestoresTheContextWithTheEventIdAsCausation(): void
    {
        $context = new CorrelationContext();
        $event = new FixtureIntegrationEvent('01900000-0000-7000-8000-0000000000bb');
        $envelope = new Envelope($event, [new CorrelationStamp('corr', 'older-cause'), new ReceivedStamp('async')]);
        $seen = null;

        $this->middleware($context)->handle($envelope, $this->capturing($seen));

        self::assertSame('corr', $context->correlationId());
        self::assertSame('01900000-0000-7000-8000-0000000000bb', $context->causationId());
    }

    public function testReceivedNonEventKeepsTheStampsCausation(): void
    {
        $context = new CorrelationContext();
        $envelope = new Envelope(new RecordingCommand('x'), [new CorrelationStamp('corr', 'cause'), new ReceivedStamp('async')]);
        $seen = null;

        $this->middleware($context)->handle($envelope, $this->capturing($seen));

        self::assertSame('cause', $context->causationId());
    }

    public function testReceivedMessageWithoutStampLeavesTheContextAlone(): void
    {
        $context = new CorrelationContext();
        $seen = null;

        $this->middleware($context)->handle(new Envelope(new RecordingCommand('x'), [new ReceivedStamp('async')]), $this->capturing($seen));

        self::assertNull($context->correlationId());
    }

    public function testHttpListenerTakesTheCorrelationIdFromTheRequestAndReturnsIt(): void
    {
        $context = new CorrelationContext();
        $listener = new CorrelationHttpListener($context, new SequentialIdGenerator());
        $request = new Request();
        $request->headers->set('X-Correlation-Id', 'abc-123');

        $listener->onRequest($this->requestEvent($request, HttpKernelInterface::MAIN_REQUEST));
        $response = new Response();
        $listener->onResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame('abc-123', $context->correlationId());
        self::assertSame('abc-123', $response->headers->get('X-Correlation-Id'));
    }

    public function testHttpListenerGeneratesAnIdWhenTheHeaderIsMissing(): void
    {
        $context = new CorrelationContext();
        $listener = new CorrelationHttpListener($context, new SequentialIdGenerator());

        $listener->onRequest($this->requestEvent(new Request(), HttpKernelInterface::MAIN_REQUEST));

        self::assertSame(self::GENERATED, $context->correlationId());
    }

    public function testHttpListenerReplacesUnsafeHeaderValues(): void
    {
        foreach (["bad value\nwith newline", str_repeat('a', 101), 'with space', ''] as $unsafe) {
            $context = new CorrelationContext();
            $listener = new CorrelationHttpListener($context, new SequentialIdGenerator());
            $request = new Request();
            $request->headers->set('X-Correlation-Id', $unsafe);

            $listener->onRequest($this->requestEvent($request, HttpKernelInterface::MAIN_REQUEST));

            self::assertSame(self::GENERATED, $context->correlationId(), 'value: '.json_encode($unsafe));
        }
    }

    public function testHttpListenerIgnoresSubRequests(): void
    {
        $context = new CorrelationContext();
        $listener = new CorrelationHttpListener($context, new SequentialIdGenerator());
        $request = new Request();

        $listener->onRequest($this->requestEvent($request, HttpKernelInterface::SUB_REQUEST));
        $response = new Response();
        $listener->onResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST, $response));

        self::assertNull($context->correlationId());
        self::assertFalse($response->headers->has('X-Correlation-Id'));
    }

    public function testHttpListenerDoesNotSetTheHeaderWithoutAContext(): void
    {
        $listener = new CorrelationHttpListener(new CorrelationContext(), new SequentialIdGenerator());
        $response = new Response();

        $listener->onResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertFalse($response->headers->has('X-Correlation-Id'));
    }

    private function middleware(CorrelationContext $context): CorrelationMiddleware
    {
        return new CorrelationMiddleware($context, new SequentialIdGenerator());
    }

    private function capturing(?Envelope &$seen): StackInterface
    {
        $next = self::createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturnCallback(static function (Envelope $envelope) use (&$seen): Envelope {
            $seen = $envelope;

            return $envelope;
        });

        $stack = self::createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }

    private function requestEvent(Request $request, int $type): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type);
    }
}
