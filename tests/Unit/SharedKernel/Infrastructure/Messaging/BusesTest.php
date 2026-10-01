<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Infrastructure\Messaging\MessengerCommandBus;
use App\SharedKernel\Infrastructure\Messaging\MessengerEventBus;
use App\SharedKernel\Infrastructure\Messaging\MessengerQueryBus;
use App\Tests\Support\Fixtures\Messaging\FixtureDomainException;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;
use App\Tests\Support\Fixtures\Messaging\GreetQuery;
use App\Tests\Support\Fixtures\Messaging\RecordingCommand;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

#[CoversClass(MessengerCommandBus::class)]
#[CoversClass(MessengerQueryBus::class)]
#[CoversClass(MessengerEventBus::class)]
final class BusesTest extends TestCase
{
    public function testCommandBusDispatchesTheCommand(): void
    {
        $command = new RecordingCommand('x');
        $inner = self::createMock(MessageBusInterface::class);
        $inner->expects(self::once())->method('dispatch')->with($command)->willReturn(new Envelope($command));

        new MessengerCommandBus($inner)->dispatch($command);
    }

    public function testCommandBusRethrowsTheOriginalException(): void
    {
        $command = new RecordingCommand('x');
        $original = new FixtureDomainException('boom');
        $inner = self::createStub(MessageBusInterface::class);
        $inner->method('dispatch')->willThrowException(new HandlerFailedException(new Envelope($command), ['handler' => $original]));

        try {
            new MessengerCommandBus($inner)->dispatch($command);
            self::fail('Expected the handler exception to be rethrown');
        } catch (Throwable $thrown) {
            self::assertSame($original, $thrown);
        }
    }

    public function testCommandBusLetsOtherExceptionsThrough(): void
    {
        $inner = self::createStub(MessageBusInterface::class);
        $inner->method('dispatch')->willThrowException(new LogicException('middleware failure'));

        $this->expectException(LogicException::class);

        new MessengerCommandBus($inner)->dispatch(new RecordingCommand('x'));
    }

    public function testQueryBusReturnsTheHandlersResult(): void
    {
        $query = new GreetQuery('Ana');
        $inner = self::createStub(MessageBusInterface::class);
        $inner->method('dispatch')->willReturn(new Envelope($query, [new HandledStamp('Hello Ana', 'handler')]));

        self::assertSame('Hello Ana', new MessengerQueryBus($inner)->ask($query));
    }

    public function testQueryBusReturnsNullWhenNothingHandledTheQuery(): void
    {
        $query = new GreetQuery('Ana');
        $inner = self::createStub(MessageBusInterface::class);
        $inner->method('dispatch')->willReturn(new Envelope($query));

        self::assertNull(new MessengerQueryBus($inner)->ask($query));
    }

    public function testQueryBusRethrowsTheOriginalException(): void
    {
        $query = new GreetQuery('Ana');
        $original = new FixtureDomainException('boom');
        $inner = self::createStub(MessageBusInterface::class);
        $inner->method('dispatch')->willThrowException(new HandlerFailedException(new Envelope($query), ['handler' => $original]));

        try {
            new MessengerQueryBus($inner)->ask($query);
            self::fail('Expected the handler exception to be rethrown');
        } catch (Throwable $thrown) {
            self::assertSame($original, $thrown);
        }
    }

    public function testEventBusDispatchesEveryEventInOrder(): void
    {
        $first = new FixtureIntegrationEvent('01900000-0000-7000-8000-000000000001');
        $second = new FixtureIntegrationEvent('01900000-0000-7000-8000-000000000002');
        $dispatched = [];
        $inner = self::createStub(MessageBusInterface::class);
        $inner->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        new MessengerEventBus($inner)->publish($first, $second);

        self::assertSame([$first, $second], $dispatched);
    }

    public function testEventBusWithoutEventsDispatchesNothing(): void
    {
        $inner = self::createMock(MessageBusInterface::class);
        $inner->expects(self::never())->method('dispatch');

        new MessengerEventBus($inner)->publish();
    }
}
