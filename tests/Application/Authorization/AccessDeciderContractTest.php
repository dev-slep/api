<?php

declare(strict_types=1);

namespace App\Tests\Application\Authorization;

use App\Authorization\Application\Command\GrantRole;
use App\Authorization\Application\ContractImplementation\AccessDeciderFacade;
use App\Authorization\Contract\AccessDecider;
use App\Authorization\Contract\Permission;
use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Contract\UserId;
use App\Tests\Support\ApplicationTestCase;
use App\Tests\Support\Attribute\CoversContractMethod;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AccessDeciderFacade::class)]
#[CoversContractMethod(AccessDecider::class, 'isGranted')]
#[CoversContractMethod(AccessDecider::class, 'allows')]
final class AccessDeciderContractTest extends ApplicationTestCase
{
    private const string ADMIN = '01900000-0000-7000-8000-0000000000a1';
    private const string DRIVER = '01900000-0000-7000-8000-0000000000a2';
    private const string TOWER = '01900000-0000-7000-8000-0000000000a3';
    private const string NOBODY = '01900000-0000-7000-8000-0000000000a4';

    private function decider(): AccessDecider
    {
        $bus = static::getContainer()->get(CommandBus::class);
        $bus->dispatch(new GrantRole(self::ADMIN, 'ADMIN'));
        $bus->dispatch(new GrantRole(self::DRIVER, 'DRIVER'));
        $bus->dispatch(new GrantRole(self::TOWER, 'TOWER'));

        return static::getContainer()->get(AccessDecider::class);
    }

    public function testTheStoredRoleDecidesForAUser(): void
    {
        $decider = $this->decider();

        self::assertTrue($decider->isGranted(new UserId(self::ADMIN), Permission::ReadAuditLog));
        self::assertFalse($decider->isGranted(new UserId(self::DRIVER), Permission::ReadAuditLog));
        self::assertFalse($decider->isGranted(new UserId(self::TOWER), Permission::ReadAuditLog));
    }

    public function testAUserWithoutARoleIsRefused(): void
    {
        self::assertFalse($this->decider()->isGranted(new UserId(self::NOBODY), Permission::ReadAuditLog));
    }

    public function testTheRolesOfATokenDecideWithoutTheDatabase(): void
    {
        $decider = $this->decider();

        self::assertTrue($decider->allows(['ROLE_ADMIN'], Permission::ReadAuditLog));
        self::assertTrue($decider->allows(['ROLE_DRIVER', 'ROLE_ADMIN'], Permission::ReadAuditLog));
        self::assertFalse($decider->allows(['ROLE_DRIVER'], Permission::ReadAuditLog));
        self::assertFalse($decider->allows(['ROLE_TOWER'], Permission::ReadAuditLog));
        self::assertFalse($decider->allows([], Permission::ReadAuditLog), 'a second-factor-pending token has no roles');
        self::assertFalse($decider->allows(['ROLE_SUPERUSER', 'admin'], Permission::ReadAuditLog), 'unknown roles are ignored');
    }
}
