<?php

declare(strict_types=1);

namespace App\Tests\Application\Authorization;

use App\Authorization\Application\Command\GrantRole;
use App\Authorization\Application\ContractImplementation\RoleLookupFacade;
use App\Authorization\Contract\RoleLookup;
use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Contract\UserId;
use App\Tests\Support\ApplicationTestCase;
use App\Tests\Support\Attribute\CoversContractMethod;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RoleLookupFacade::class)]
#[CoversContractMethod(RoleLookup::class, 'rolesFor')]
final class RoleLookupContractTest extends ApplicationTestCase
{
    private const string GRANTED = '01900000-0000-7000-8000-0000000000aa';
    private const string UNKNOWN = '01900000-0000-7000-8000-0000000000bb';

    public function testAGrantedUserHasItsSecurityRole(): void
    {
        $bus = static::getContainer()->get(CommandBus::class);
        $bus->dispatch(new GrantRole(self::GRANTED, 'TOWER'));
        $lookup = static::getContainer()->get(RoleLookup::class);

        self::assertSame(['ROLE_TOWER'], $lookup->rolesFor(new UserId(self::GRANTED)));
    }

    public function testAUserWithoutAGrantHasNoRoles(): void
    {
        $lookup = static::getContainer()->get(RoleLookup::class);

        self::assertSame([], $lookup->rolesFor(new UserId(self::UNKNOWN)));
    }
}
