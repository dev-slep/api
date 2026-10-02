<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence;

use App\SharedKernel\Infrastructure\Persistence\DoctrineProcessedEvents;
use Doctrine\DBAL\Connection;

/**
 * Remembers which events the Authentication subscribers handled (table authentication.processed_event).
 */
final readonly class AuthenticationProcessedEvents extends DoctrineProcessedEvents
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection, 'authentication.processed_event');
    }
}
