<?php

declare(strict_types=1);

namespace App\Authorization\Infrastructure\Persistence;

use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\Role;
use App\Authorization\Domain\Model\RoleAssignment;
use App\Authorization\Domain\Model\RoleAssignmentId;
use App\Authorization\Domain\Repository\RoleAssignmentRepository;
use App\SharedKernel\Application\AggregateEventCollector;

use function assert;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

use function is_string;

/**
 * Plain SQL on "authorization".role_assignment. `authorization` is a reserved word in PostgreSQL and the ORM mapping
 * cannot quote a schema name, so this module has no ORM mapping. An assignment never changes once it is stored.
 */
final readonly class DbalRoleAssignmentRepository implements RoleAssignmentRepository
{
    private const string TIMESTAMP = 'Y-m-d H:i:s.uP';

    public function __construct(
        private Connection $connection,
        private AggregateEventCollector $collector,
    ) {
    }

    public function findByUserId(AssignedUserId $userId): ?RoleAssignment
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, role, granted_at FROM "authorization".role_assignment WHERE user_id = :userId',
            ['userId' => $userId->toString()],
        );
        if (false === $row) {
            return null;
        }

        assert(is_string($row['id']) && is_string($row['user_id']) && is_string($row['role']) && is_string($row['granted_at']));

        return RoleAssignment::reconstitute(
            new RoleAssignmentId($row['id']),
            new AssignedUserId($row['user_id']),
            Role::fromName($row['role']),
            new DateTimeImmutable($row['granted_at']),
        );
    }

    public function save(RoleAssignment $assignment): void
    {
        $this->connection->executeStatement(
            'INSERT INTO "authorization".role_assignment (id, user_id, role, granted_at) VALUES (:id, :userId, :role, :grantedAt)',
            [
                'id' => $assignment->id()->toString(),
                'userId' => $assignment->userId()->toString(),
                'role' => $assignment->role()->value,
                'grantedAt' => $assignment->grantedAt()->setTimezone(new DateTimeZone('UTC'))->format(self::TIMESTAMP),
            ],
        );

        $this->collector->collect($assignment);
    }
}
