<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authorization\Application;

use App\Authorization\Application\ContractImplementation\AccessDeciderFacade;
use App\Authorization\Application\Service\PermissionCatalogue;
use App\Authorization\Contract\Permission;
use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\Role;
use App\Authorization\Domain\Model\RoleAssignment;
use App\Authorization\Domain\Model\RoleAssignmentId;
use App\Authorization\Infrastructure\Persistence\InMemoryRoleAssignmentRepository;
use App\SharedKernel\Contract\UserId;
use App\SharedKernel\Infrastructure\Messaging\CollectedAggregateEvents;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionCatalogue::class)]
#[CoversClass(AccessDeciderFacade::class)]
final class PermissionCatalogueTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';

    public function testEveryPermissionIsHeldByAtLeastOneRole(): void
    {
        self::assertSame([], (new PermissionCatalogue())->unmapped());
    }

    public function testAdminsReadTheAuditLogAndNobodyElseDoes(): void
    {
        $policy = (new PermissionCatalogue())->policy();

        self::assertSame([Role::Admin], $policy->rolesHolding(Permission::ReadAuditLog->value));
    }

    public function testTheDeciderUsesTheStoredRoleAndTheTokenRoles(): void
    {
        $assignments = new InMemoryRoleAssignmentRepository(new CollectedAggregateEvents());
        $assignments->save(RoleAssignment::grant(new RoleAssignmentId('01900000-0000-7000-8000-000000000001'), new AssignedUserId(self::USER), Role::Admin, new DateTimeImmutable()));
        $lookup = new \App\Authorization\Application\ContractImplementation\RoleLookupFacade($assignments);
        $decider = new AccessDeciderFacade($lookup, new PermissionCatalogue());

        self::assertTrue($decider->isGranted(new UserId(self::USER), Permission::ReadAuditLog));
        self::assertFalse($decider->isGranted(new UserId('01900000-0000-7000-8000-0000000000bb'), Permission::ReadAuditLog));
        self::assertTrue($decider->allows(['ROLE_ADMIN'], Permission::ReadAuditLog));
        self::assertFalse($decider->allows(['ROLE_DRIVER'], Permission::ReadAuditLog));
    }
}
