<?php

declare(strict_types=1);

namespace App\Authorization\Domain\Event;

use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\Role;
use App\SharedKernel\Domain\DomainEvent;
use DateTimeImmutable;

final readonly class RoleGranted implements DomainEvent
{
    public function __construct(
        public AssignedUserId $userId,
        public Role $role,
        public DateTimeImmutable $at,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
