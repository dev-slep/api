<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure;

use App\SharedKernel\Infrastructure\Persistence\DoctrineProcessedEvents;
use App\Tests\Support\Fixtures\Outbox\FixtureProcessedEvents;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineProcessedEvents::class)]
final class DoctrineProcessedEventsTest extends TestCase
{
    public function testWasProcessedIsTrueWhenARowExists(): void
    {
        $connection = self::createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')
            ->with(self::stringContains('FROM public.processed_event_fixture'), ['subscriber' => 'billing', 'eventId' => 'e1'])
            ->willReturn(1);

        self::assertTrue(new FixtureProcessedEvents($connection)->wasProcessed('billing', 'e1'));
    }

    public function testWasProcessedIsFalseWhenNoRowExists(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        self::assertFalse(new FixtureProcessedEvents($connection)->wasProcessed('billing', 'e1'));
    }

    public function testMarkProcessedInsertsIdempotently(): void
    {
        $connection = self::createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')
            ->with(self::stringContains('ON CONFLICT DO NOTHING'), ['subscriber' => 'billing', 'eventId' => 'e1'])
            ->willReturn(1);

        new FixtureProcessedEvents($connection)->markProcessed('billing', 'e1');
    }
}
