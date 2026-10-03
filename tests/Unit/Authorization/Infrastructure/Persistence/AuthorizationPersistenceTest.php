<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authorization\Infrastructure\Persistence;

use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\Role;
use App\Authorization\Domain\Model\RoleAssignment;
use App\Authorization\Domain\Model\RoleAssignmentId;
use App\Authorization\Infrastructure\Persistence\AuthorizationProcessedEvents;
use App\Authorization\Infrastructure\Persistence\DbalRoleAssignmentRepository;
use App\SharedKernel\Infrastructure\Messaging\CollectedAggregateEvents;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The behaviour of the SQL itself is covered by the integration test; here the statements are checked against a double.
 */
#[CoversClass(DbalRoleAssignmentRepository::class)]
#[CoversClass(AuthorizationProcessedEvents::class)]
final class AuthorizationPersistenceTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';
    private const string ID = '01900000-0000-7000-8000-000000000001';

    public function testTheSchemaNameIsQuotedBecauseItIsAReservedWord(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')
            ->with(self::stringContains('FROM "authorization".role_assignment'), ['userId' => self::USER])
            ->willReturn(['id' => self::ID, 'user_id' => self::USER, 'role' => 'TOWER', 'granted_at' => '2026-01-01 12:00:00.000000+00']);

        $assignment = (new DbalRoleAssignmentRepository($connection, new CollectedAggregateEvents()))->findByUserId(new AssignedUserId(self::USER));

        self::assertSame(Role::Tower, $assignment?->role());
        self::assertSame(self::ID, $assignment->id()->toString());
        self::assertEquals(new DateTimeImmutable('2026-01-01T12:00:00+00:00'), $assignment->grantedAt());
    }

    public function testAMissingRowGivesNull(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        self::assertNull((new DbalRoleAssignmentRepository($connection, new CollectedAggregateEvents()))->findByUserId(new AssignedUserId(self::USER)));
    }

    public function testSavingInsertsOnceInUtcAndRegistersTheEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->with(
            self::stringContains('INSERT INTO "authorization".role_assignment'),
            ['id' => self::ID, 'userId' => self::USER, 'role' => 'ADMIN', 'grantedAt' => '2026-01-01 11:00:00.000000+00:00'],
        );
        $collector = new CollectedAggregateEvents();

        (new DbalRoleAssignmentRepository($connection, $collector))->save(
            RoleAssignment::grant(new RoleAssignmentId(self::ID), new AssignedUserId(self::USER), Role::Admin, new DateTimeImmutable('2026-01-01T12:00:00+01:00')),
        );

        self::assertCount(1, $collector->releaseEvents());
    }

    public function testTheProcessedEventsTableIsTheModulesOwn(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with(self::stringContains('"authorization".processed_event'))->willReturn(false);

        self::assertFalse((new AuthorizationProcessedEvents($connection))->wasProcessed('sub', 'event'));
    }
}
