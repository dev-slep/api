<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Application\EventBus;
use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Domain\DomainEvent;
use App\SharedKernel\Infrastructure\Messaging\CollectedAggregateEvents;
use App\SharedKernel\Infrastructure\Messaging\OutboxMiddleware;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;
use App\Tests\Support\Fixtures\Messaging\RecordingCommand;
use App\Tests\Support\Fixtures\Outbox\FixtureAggregate;
use App\Tests\Support\Fixtures\Outbox\FixtureHappened;
use App\Tests\Support\Fixtures\Outbox\FixtureHappenedMapper;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

#[CoversClass(CollectedAggregateEvents::class)]
#[CoversClass(OutboxMiddleware::class)]
final class OutboxTest extends TestCase
{
    public function testCollectorReleasesTheEventsOfAllCollectedAggregatesInOrder(): void
    {
        $collector = new CollectedAggregateEvents();
        $collector->collect(new FixtureAggregate('a'));
        $collector->collect(new FixtureAggregate('b'));

        $events = $collector->releaseEvents();

        self::assertSame(['a', 'b'], array_map(static fn (DomainEvent $event): string => $event instanceof FixtureHappened ? $event->aggregateId : '?', $events));
    }

    public function testCollectingTheSameAggregateTwiceReleasesItsEventsOnce(): void
    {
        $collector = new CollectedAggregateEvents();
        $aggregate = new FixtureAggregate('a');

        $collector->collect($aggregate);
        $collector->collect($aggregate);

        self::assertCount(1, $collector->releaseEvents());
    }

    public function testReleasingClearsTheCollector(): void
    {
        $collector = new CollectedAggregateEvents();
        $collector->collect(new FixtureAggregate('a'));
        $collector->releaseEvents();

        self::assertSame([], $collector->releaseEvents());
    }

    public function testResetForgetsCollectedAggregates(): void
    {
        $collector = new CollectedAggregateEvents();
        $collector->collect(new FixtureAggregate('a'));

        $collector->reset();

        self::assertSame([], $collector->releaseEvents());
    }

    public function testOutboxPublishesMappedIntegrationEventsAfterTheHandlerSucceeded(): void
    {
        $collector = new CollectedAggregateEvents();
        $published = [];
        $middleware = $this->middleware($collector, $published);

        $envelope = new Envelope(new RecordingCommand('x'));
        $result = $middleware->handle($envelope, $this->stack(static function () use ($collector, $envelope): Envelope {
            $collector->collect(new FixtureAggregate('a'));

            return $envelope;
        }));

        self::assertSame($envelope, $result);
        self::assertCount(1, $published);
        self::assertSame('a', $published[0]->eventId());
    }

    public function testOutboxPublishesNothingWhenNoAggregateWasCollected(): void
    {
        $published = [];
        $middleware = $this->middleware(new CollectedAggregateEvents(), $published);
        $envelope = new Envelope(new RecordingCommand('x'));

        $middleware->handle($envelope, $this->stack(static fn (): Envelope => $envelope));

        self::assertSame([], $published);
    }

    public function testEventsWithoutASupportingMapperAreDropped(): void
    {
        $collector = new CollectedAggregateEvents();
        $collector->collect(new FixtureAggregate('a'));
        $published = [];
        $middleware = new OutboxMiddleware($collector, $this->eventBus($published), []);
        $envelope = new Envelope(new RecordingCommand('x'));

        $middleware->handle($envelope, $this->stack(static fn (): Envelope => $envelope));

        self::assertSame([], $published);
    }

    public function testNothingIsPublishedAndStateIsClearedWhenTheHandlerFails(): void
    {
        $collector = new CollectedAggregateEvents();
        $published = [];
        $middleware = $this->middleware($collector, $published);
        $envelope = new Envelope(new RecordingCommand('x'));

        try {
            $middleware->handle($envelope, $this->stack(static function () use ($collector): never {
                $collector->collect(new FixtureAggregate('a'));

                throw new RuntimeException('handler failed');
            }));
            self::fail('Expected the handler failure to propagate');
        } catch (RuntimeException) {
        }

        self::assertSame([], $published);
        self::assertSame([], $collector->releaseEvents(), 'no events may leak into the next command');
    }

    public function testFailureWhilePublishingPropagatesSoTheTransactionRollsBack(): void
    {
        $collector = new CollectedAggregateEvents();
        $eventBus = self::createStub(EventBus::class);
        $eventBus->method('publish')->willThrowException(new RuntimeException('transport down'));
        $middleware = new OutboxMiddleware($collector, $eventBus, [new FixtureHappenedMapper()]);
        $envelope = new Envelope(new RecordingCommand('x'));

        $this->expectExceptionMessage('transport down');

        $middleware->handle($envelope, $this->stack(static function () use ($collector, $envelope): Envelope {
            $collector->collect(new FixtureAggregate('a'));

            return $envelope;
        }));
    }

    public function testMessagesThatAreNotCommandsPassThroughUntouched(): void
    {
        $collector = new CollectedAggregateEvents();
        $collector->collect(new FixtureAggregate('a'));
        $published = [];
        $middleware = $this->middleware($collector, $published);
        $envelope = new Envelope(new FixtureIntegrationEvent());

        $result = $middleware->handle($envelope, $this->stack(static fn (): Envelope => $envelope));

        self::assertSame($envelope, $result);
        self::assertSame([], $published);
        self::assertCount(1, $collector->releaseEvents(), 'events of the surrounding command stay collected');
    }

    /**
     * @param list<IntegrationEvent> $published
     */
    private function middleware(CollectedAggregateEvents $collector, array &$published): OutboxMiddleware
    {
        return new OutboxMiddleware($collector, $this->eventBus($published), [new FixtureHappenedMapper()]);
    }

    /**
     * @param list<IntegrationEvent> $published
     */
    private function eventBus(array &$published): EventBus
    {
        $eventBus = self::createStub(EventBus::class);
        $eventBus->method('publish')->willReturnCallback(static function (IntegrationEvent ...$events) use (&$published): void {
            array_push($published, ...$events);
        });

        return $eventBus;
    }

    /**
     * @param Closure(): Envelope $handler
     */
    private function stack(Closure $handler): StackInterface
    {
        $next = self::createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturnCallback($handler);

        $stack = self::createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }
}
