<?php

declare(strict_types=1);

namespace App\Tests\Application\Authorization;

use App\Authorization\Application\ContractImplementation\NoRolesGrantedYet;
use App\Authorization\Contract\RoleLookup;
use App\SharedKernel\Contract\UserId;
use App\Tests\Support\ApplicationTestCase;
use App\Tests\Support\Attribute\CoversContractMethod;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NoRolesGrantedYet::class)]
#[CoversContractMethod(RoleLookup::class, 'rolesFor')]
final class RoleLookupContractTest extends ApplicationTestCase
{
    public function testUntilAuthorizationStoresRolesNoneAreGranted(): void
    {
        $lookup = static::getContainer()->get(RoleLookup::class);

        self::assertSame([], $lookup->rolesFor(new UserId('01900000-0000-7000-8000-0000000000aa')));
        self::assertSame([], $lookup->rolesFor(new UserId('01900000-0000-7000-8000-0000000000bb')));
    }
}
