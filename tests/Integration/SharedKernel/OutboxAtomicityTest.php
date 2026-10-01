<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Application\IdempotentHandling;
use App\SharedKernel\Application\Transaction;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;
use App\Tests\Support\Fixtures\Outbox\CreateFixtureCommand;
use App\Tests\Support\Fixtures\Outbox\FixtureProcessedEvents;
use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\TruncatesSchemas;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use RuntimeException;

#[CoversNothing]
#[SkipDatabaseRollback]
final class OutboxAtomicityTest extends IntegrationTestCase
{
    use TruncatesSchemas;

    private const string ID = '01900000-0000-7000-8000-0000000000d1';

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->connection();
        $connection->executeStatement('DROP TABLE IF EXISTS public.outbox_fixture, public.processed_event_fixture');
        $connection->executeStatement('CREATE TABLE public.outbox_fixture (id TEXT PRIMARY KEY)');
        $connection->executeStatement('CREATE TABLE public.processed_event_fixture (subscriber TEXT NOT NULL, event_id TEXT NOT NULL, processed_at TIMESTAMPTZ NOT NULL, PRIMARY KEY (subscriber, event_id))');
        $this->truncateSchemas();
    }

    protected function tearDown(): void
    {
        $this->truncateSchemas();
        $this->connection()->executeStatement('DROP TABLE IF EXISTS public.outbox_fixture, public.processed_event_fixture');

        parent::tearDown();
    }

    public function testStateAndEventAreBothStoredWhenTheHandlerSucceeds(): void
    {
        $this->commandBus()->dispatch(new CreateFixtureCommand(self::ID, insertRow: true));

        self::assertSame(1, $this->rows('public.outbox_fixture'));
        self::assertSame(1, $this->rows('messenger.messenger_messages'));
    }

    public function testNeitherStateNorEventIsStoredWhenTheHandlerFailsAfterRecordingEvents(): void
    {
        try {
            $this->commandBus()->dispatch(new CreateFixtureCommand(self::ID, failAfterRecording: true, insertRow: true));
            self::fail('Expected the handler failure to propagate');
        } catch (RuntimeException) {
        }

        self::assertSame(0, $this->rows('public.outbox_fixture'), 'no phantom state');
        self::assertSame(0, $this->rows('messenger.messenger_messages'), 'no phantom event');
    }

    public function testStoredEventCarriesTheCorrelationStamp(): void
    {
        $this->commandBus()->dispatch(new CreateFixtureCommand(self::ID));

        $body = $this->connection()->fetchOne('SELECT body FROM messenger.messenger_messages');

        self::assertIsString($body);
        self::assertStringContainsString('CorrelationStamp', $body);
    }

    public function testARedeliveredEventIsProcessedOnlyOnce(): void
    {
        $handling = new IdempotentHandling(
            static::getContainer()->get(FixtureProcessedEvents::class),
            static::getContainer()->get(Transaction::class),
        );
        $event = new FixtureIntegrationEvent(self::ID);
        $runs = 0;
        $work = static function () use (&$runs): void {
            ++$runs;
        };

        $handling->handle('fixture-subscriber', $event, $work);
        $handling->handle('fixture-subscriber', $event, $work);

        self::assertSame(1, $runs);
        self::assertSame(1, $this->rows('public.processed_event_fixture'));
    }

    public function testWorkThatFailsLeavesNoProcessedMarker(): void
    {
        $handling = new IdempotentHandling(
            static::getContainer()->get(FixtureProcessedEvents::class),
            static::getContainer()->get(Transaction::class),
        );

        try {
            $handling->handle('fixture-subscriber', new FixtureIntegrationEvent(self::ID), static function (): never {
                throw new RuntimeException('boom');
            });
            self::fail('Expected the work failure to propagate');
        } catch (RuntimeException) {
        }

        self::assertSame(0, $this->rows('public.processed_event_fixture'));
    }

    private function commandBus(): CommandBus
    {
        return static::getContainer()->get(CommandBus::class);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get('doctrine.dbal.default_connection');
    }

    private function rows(string $table): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsInt($count);

        return $count;
    }
}
