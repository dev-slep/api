<?php

declare(strict_types=1);

namespace App\Authorization\Infrastructure\Persistence;

use App\SharedKernel\Infrastructure\Persistence\DoctrineProcessedEvents;
use Doctrine\DBAL\Connection;

/**
 * Remembers which events the Authorization subscribers handled (table authorization.processed_event).
 */
final readonly class AuthorizationProcessedEvents extends DoctrineProcessedEvents
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection, '"authorization".processed_event');
    }
}
