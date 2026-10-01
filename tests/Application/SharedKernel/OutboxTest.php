<?php

declare(strict_types=1);

namespace App\Tests\Application\SharedKernel;

use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Infrastructure\Correlation\CorrelationContext;
use App\SharedKernel\Infrastructure\Correlation\CorrelationStamp;
use App\Tests\Support\ApplicationTestCase;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;
use App\Tests\Support\Fixtures\Outbox\CreateFixtureCommand;
use PHPUnit\Framework\Attributes\CoversNothing;
use RuntimeException;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

#[CoversNothing]
final class OutboxTest extends ApplicationTestCase
{
    public function testSavedAggregateProducesTheMappedIntegrationEvent(): void
    {
        static::getContainer()->get(CommandBus::class)->dispatch(new CreateFixtureCommand('01900000-0000-7000-8000-0000000000c1'));

        $events = $this->dispatchedEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(FixtureIntegrationEvent::class, $events[0]);
        self::assertSame('01900000-0000-7000-8000-0000000000c1', $events[0]->eventId());
    }

    public function testNoIntegrationEventIsPublishedWhenTheHandlerFails(): void
    {
        try {
            static::getContainer()->get(CommandBus::class)->dispatch(new CreateFixtureCommand('01900000-0000-7000-8000-0000000000c2', failAfterRecording: true));
            self::fail('Expected the handler failure to propagate');
        } catch (RuntimeException) {
        }

        self::assertSame([], $this->dispatchedEvents());
    }

    public function testPublishedEventCarriesTheCorrelationOfTheCommand(): void
    {
        static::getContainer()->get(CorrelationContext::class)->start('request-42');

        static::getContainer()->get(CommandBus::class)->dispatch(new CreateFixtureCommand('01900000-0000-7000-8000-0000000000c3'));

        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $stamp = $transport->getSent()[0]->last(CorrelationStamp::class);
        self::assertSame('request-42', $stamp?->correlationId);
    }

    public function testEventsOfOneCommandDoNotLeakIntoTheNext(): void
    {
        $bus = static::getContainer()->get(CommandBus::class);

        $bus->dispatch(new CreateFixtureCommand('01900000-0000-7000-8000-0000000000c4'));
        $bus->dispatch(new CreateFixtureCommand('01900000-0000-7000-8000-0000000000c5'));

        self::assertSame(
            ['01900000-0000-7000-8000-0000000000c4', '01900000-0000-7000-8000-0000000000c5'],
            array_map(static fn (object $event): string => $event instanceof FixtureIntegrationEvent ? $event->eventId() : '?', $this->dispatchedEvents()),
        );
    }
}
