<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Domain\DomainEvent;

/**
 * Maps a module's domain events to its public, versioned integration events.
 * Implementations are tagged through DI and consulted by the outbox middleware.
 */
interface DomainEventMapper
{
    public function supports(DomainEvent $event): bool;

    /**
     * @return list<IntegrationEvent>
     */
    public function map(DomainEvent $event): array;
}
