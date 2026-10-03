<?php

declare(strict_types=1);

namespace App\Authorization\Infrastructure\Persistence;

use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\RoleAssignment;
use App\Authorization\Domain\Repository\RoleAssignmentRepository;
use App\SharedKernel\Application\AggregateEventCollector;

/**
 * Used by application tests, which never touch the database.
 */
final class InMemoryRoleAssignmentRepository implements RoleAssignmentRepository
{
    /** @var array<string, RoleAssignment> keyed by user id */
    private array $assignments = [];

    public function __construct(private readonly AggregateEventCollector $collector)
    {
    }

    public function findByUserId(AssignedUserId $userId): ?RoleAssignment
    {
        return $this->assignments[$userId->toString()] ?? null;
    }

    public function save(RoleAssignment $assignment): void
    {
        $this->assignments[$assignment->userId()->toString()] = $assignment;
        $this->collector->collect($assignment);
    }
}
