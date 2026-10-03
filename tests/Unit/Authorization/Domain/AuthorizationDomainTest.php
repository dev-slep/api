<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authorization\Domain;

use App\Authorization\Domain\Event\RoleGranted;
use App\Authorization\Domain\Exception\UnknownRole;
use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\Role;
use App\Authorization\Domain\Model\RoleAssignment;
use App\Authorization\Domain\Model\RoleAssignmentId;
use App\Authorization\Domain\Policy\RolePermissions;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Role::class)]
#[CoversClass(RoleAssignment::class)]
#[CoversClass(RoleGranted::class)]
#[CoversClass(RolePermissions::class)]
#[CoversClass(UnknownRole::class)]
final class AuthorizationDomainTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';
    private const string ASSIGNMENT = '01900000-0000-7000-8000-000000000001';

    public function testEveryRoleKnowsItsSecurityRole(): void
    {
        self::assertSame('ROLE_DRIVER', Role::Driver->securityRole());
        self::assertSame('ROLE_TOWER', Role::Tower->securityRole());
        self::assertSame('ROLE_ADMIN', Role::Admin->securityRole());
    }

    public function testARoleIsFoundByItsName(): void
    {
        self::assertSame(Role::Tower, Role::fromName('TOWER'));
    }

    public function testAnUnknownRoleNameIsRefused(): void
    {
        $this->expectException(UnknownRole::class);
        $this->expectExceptionMessage('"SUPERUSER" is not a known role.');

        Role::fromName('SUPERUSER');
    }

    public function testGrantingARoleRecordsOneEvent(): void
    {
        $now = new DateTimeImmutable('2026-01-02T10:00:00+00:00');

        $assignment = RoleAssignment::grant(new RoleAssignmentId(self::ASSIGNMENT), new AssignedUserId(self::USER), Role::Driver, $now);

        self::assertSame(self::ASSIGNMENT, $assignment->id()->toString());
        self::assertSame(self::USER, $assignment->userId()->toString());
        self::assertSame(Role::Driver, $assignment->role());
        self::assertSame($now, $assignment->grantedAt());
        $events = $assignment->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleGranted::class, $events[0]);
        self::assertSame(Role::Driver, $events[0]->role);
        self::assertSame(self::USER, $events[0]->userId->toString());
        self::assertSame($now, $events[0]->occurredAt());
        self::assertSame([], $assignment->releaseEvents());
    }

    public function testAReconstitutedAssignmentRecordsNothing(): void
    {
        $assignment = RoleAssignment::reconstitute(new RoleAssignmentId(self::ASSIGNMENT), new AssignedUserId(self::USER), Role::Admin, new DateTimeImmutable('2026-01-02T10:00:00+00:00'));

        self::assertSame(Role::Admin, $assignment->role());
        self::assertSame([], $assignment->releaseEvents());
    }

    public function testPermissionsAreHeldByTheRolesTheyAreMappedTo(): void
    {
        $permissions = new RolePermissions(['audit.read' => [Role::Admin], 'bid.place' => [Role::Tower]]);

        self::assertSame([Role::Admin], $permissions->rolesHolding('audit.read'));
        self::assertSame([], $permissions->rolesHolding('unknown'));
        self::assertTrue($permissions->isHeldBy('audit.read', [Role::Driver, Role::Admin]));
        self::assertFalse($permissions->isHeldBy('audit.read', [Role::Driver, Role::Tower]));
        self::assertFalse($permissions->isHeldBy('audit.read', []));
        self::assertFalse($permissions->isHeldBy('unknown', [Role::Admin]));
    }
}
