<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence;

use App\SharedKernel\Infrastructure\Persistence\DoctrineProcessedEvents;
use Doctrine\DBAL\Connection;

/**
 * Remembers which events the Audit subscribers handled (table audit.processed_event).
 */
final readonly class AuditProcessedEvents extends DoctrineProcessedEvents
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection, 'audit.processed_event');
    }
}
