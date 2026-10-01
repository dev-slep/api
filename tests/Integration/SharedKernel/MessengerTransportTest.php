<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\SharedKernel\Application\EventBus;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;
use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\TruncatesSchemas;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
#[SkipDatabaseRollback]
final class MessengerTransportTest extends IntegrationTestCase
{
    use TruncatesSchemas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateSchemas();
    }

    protected function tearDown(): void
    {
        $this->truncateSchemas();

        parent::tearDown();
    }

    public function testPublishedIntegrationEventIsStoredInTheMessengerSchema(): void
    {
        $eventBus = static::getContainer()->get(EventBus::class);

        $eventBus->publish(new FixtureIntegrationEvent());

        self::assertSame(['default' => 1], $this->messagesPerQueue());
    }

    public function testMessageThatKeepsFailingEndsUpInTheFailedTransportAfterItsRetries(): void
    {
        $eventBus = static::getContainer()->get(EventBus::class);
        $eventBus->publish(new FixtureIntegrationEvent());

        $output = $this->consumeMessages('async', 5);

        self::assertSame(['failed' => 1], $this->messagesPerQueue(), $output);
    }

    /**
     * @return array<string, int>
     */
    private function messagesPerQueue(): array
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        /** @var array<string, int|string> $rows */
        $rows = $connection->fetchAllKeyValue('SELECT queue_name, COUNT(*) FROM messenger.messenger_messages GROUP BY queue_name');

        return array_map(intval(...), $rows);
    }
}
