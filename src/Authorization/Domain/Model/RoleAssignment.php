<?php

declare(strict_types=1);

namespace App\Authorization\Domain\Model;

use App\Authorization\Domain\Event\RoleGranted;
use App\SharedKernel\Domain\AggregateRoot;
use DateTimeImmutable;

/**
 * The role of one user. Exactly one assignment exists per user.
 */
final class RoleAssignment extends AggregateRoot
{
    private function __construct(
        private readonly RoleAssignmentId $id,
        private readonly AssignedUserId $userId,
        private readonly Role $role,
        private readonly DateTimeImmutable $grantedAt,
    ) {
    }

    public static function grant(RoleAssignmentId $id, AssignedUserId $userId, Role $role, DateTimeImmutable $now): self
    {
        $assignment = new self($id, $userId, $role, $now);
        $assignment->recordThat(new RoleGranted($userId, $role, $now));

        return $assignment;
    }

    public static function reconstitute(RoleAssignmentId $id, AssignedUserId $userId, Role $role, DateTimeImmutable $grantedAt): self
    {
        return new self($id, $userId, $role, $grantedAt);
    }

    public function id(): RoleAssignmentId
    {
        return $this->id;
    }

    public function userId(): AssignedUserId
    {
        return $this->userId;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function grantedAt(): DateTimeImmutable
    {
        return $this->grantedAt;
    }
}
