<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Outbox;

use App\SharedKernel\Infrastructure\Persistence\DoctrineProcessedEvents;
use Doctrine\DBAL\Connection;

/**
 * Test-only concrete subclass over the test table public.processed_event_fixture.
 */
final readonly class FixtureProcessedEvents extends DoctrineProcessedEvents
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection, 'public.processed_event_fixture');
    }
}
