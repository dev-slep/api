<?php

declare(strict_types=1);

namespace App\Authorization\Application\Service;

use App\Authorization\Contract\Event\RoleGrantedV1;
use App\Authorization\Domain\Event\RoleGranted;
use App\SharedKernel\Application\DomainEventMapper;
use App\SharedKernel\Contract\UserId;
use App\SharedKernel\Domain\DomainEvent;
use App\SharedKernel\Domain\IdGenerator;

/**
 * Turns the role assignment's domain events into the module's public integration events.
 */
final readonly class AuthorizationEventMapper implements DomainEventMapper
{
    public function __construct(private IdGenerator $ids)
    {
    }

    public function supports(DomainEvent $event): bool
    {
        return $event instanceof RoleGranted;
    }

    public function map(DomainEvent $event): array
    {
        if (!$event instanceof RoleGranted) {
            return [];
        }

        return [new RoleGrantedV1($this->ids->generate(), $event->occurredAt(), new UserId($event->userId->toString()), $event->role->value)];
    }
}
