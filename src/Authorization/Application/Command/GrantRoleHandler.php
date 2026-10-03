<?php

declare(strict_types=1);

namespace App\Authorization\Application\Command;

use App\Authorization\Domain\Exception\UnknownRole;
use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\Role;
use App\Authorization\Domain\Model\RoleAssignment;
use App\Authorization\Domain\Model\RoleAssignmentId;
use App\Authorization\Domain\Repository\RoleAssignmentRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;

final readonly class GrantRoleHandler implements CommandHandler
{
    public function __construct(
        private RoleAssignmentRepository $assignments,
        private Clock $clock,
        private IdGenerator $ids,
    ) {
    }

    /**
     * Idempotent: a user keeps the role they were first given, and granting again changes and publishes nothing.
     *
     * @throws UnknownRole
     */
    public function __invoke(GrantRole $command): void
    {
        $role = Role::fromName($command->role);
        $userId = new AssignedUserId($command->userId);

        if (null !== $this->assignments->findByUserId($userId)) {
            return;
        }

        $this->assignments->save(RoleAssignment::grant(new RoleAssignmentId($this->ids->generate()), $userId, $role, $this->clock->now()));
    }
}
