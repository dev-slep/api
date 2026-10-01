<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Persistence;

use App\SharedKernel\Application\ProcessedEvents;
use Doctrine\DBAL\Connection;

use function sprintf;

/**
 * Base for the per-module implementations: each module adds its own `<schema>.processed_event` table
 * (columns `subscriber`, `event_id`, `processed_at`) and a concrete subclass that passes its table name.
 */
abstract readonly class DoctrineProcessedEvents implements ProcessedEvents
{
    public function __construct(
        private Connection $connection,
        private string $table,
    ) {
    }

    public function wasProcessed(string $subscriber, string $eventId): bool
    {
        return false !== $this->connection->fetchOne(
            sprintf('SELECT 1 FROM %s WHERE subscriber = :subscriber AND event_id = :eventId', $this->table),
            ['subscriber' => $subscriber, 'eventId' => $eventId],
        );
    }

    public function markProcessed(string $subscriber, string $eventId): void
    {
        $this->connection->executeStatement(
            sprintf('INSERT INTO %s (subscriber, event_id, processed_at) VALUES (:subscriber, :eventId, NOW()) ON CONFLICT DO NOTHING', $this->table),
            ['subscriber' => $subscriber, 'eventId' => $eventId],
        );
    }
}
