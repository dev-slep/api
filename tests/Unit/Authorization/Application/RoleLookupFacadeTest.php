<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authorization\Application;

use App\Authorization\Application\ContractImplementation\RoleLookupFacade;
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

#[CoversClass(RoleLookupFacade::class)]
final class RoleLookupFacadeTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';

    public function testAGrantedUserHasItsSecurityRole(): void
    {
        $assignments = new InMemoryRoleAssignmentRepository(new CollectedAggregateEvents());
        $assignments->save(RoleAssignment::grant(new RoleAssignmentId('01900000-0000-7000-8000-000000000001'), new AssignedUserId(self::USER), Role::Admin, new DateTimeImmutable()));

        self::assertSame(['ROLE_ADMIN'], (new RoleLookupFacade($assignments))->rolesFor(new UserId(self::USER)));
    }

    public function testAUserWithoutAGrantHasNoRoles(): void
    {
        $lookup = new RoleLookupFacade(new InMemoryRoleAssignmentRepository(new CollectedAggregateEvents()));

        self::assertSame([], $lookup->rolesFor(new UserId(self::USER)));
    }
}
