<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Outbox;

use App\SharedKernel\Application\AggregateEventCollector;
use App\SharedKernel\Application\CommandHandler;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * Test-only: "saves" an aggregate (a row in public.outbox_fixture, when asked to) and registers it for the outbox.
 */
final readonly class CreateFixtureHandler implements CommandHandler
{
    public function __construct(
        private AggregateEventCollector $collector,
        private Connection $connection,
    ) {
    }

    public function __invoke(CreateFixtureCommand $command): void
    {
        $aggregate = new FixtureAggregate($command->id);

        if ($command->insertRow) {
            $this->connection->insert('outbox_fixture', ['id' => $command->id]);
        }

        $this->collector->collect($aggregate);

        if ($command->failAfterRecording) {
            throw new RuntimeException('Handler failed after recording events.');
        }
    }
}
