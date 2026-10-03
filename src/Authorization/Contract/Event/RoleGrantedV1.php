<?php

declare(strict_types=1);

namespace App\Authorization\Contract\Event;

use App\SharedKernel\Contract\UserId;
use DateTimeImmutable;

final readonly class RoleGrantedV1 extends AuthorizationEvent
{
    /**
     * @param string $role "DRIVER", "TOWER" or "ADMIN"
     */
    public function __construct(
        string $eventId,
        DateTimeImmutable $occurredAt,
        public UserId $userId,
        public string $role,
    ) {
        parent::__construct($eventId, $occurredAt);
    }

    public function eventName(): string
    {
        return 'authorization.role_granted.v1';
    }
}
