<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Application;

use App\Authorization\Application\ContractImplementation\NoRolesGrantedYet;
use App\Penalty\Application\ContractImplementation\AllowAllBlacklistChecker;
use App\SharedKernel\Contract\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllowAllBlacklistChecker::class)]
#[CoversClass(NoRolesGrantedYet::class)]
final class PlaceholderImplementationsTest extends TestCase
{
    public function testTheTemporaryBlacklistBlocksNothing(): void
    {
        $checker = new AllowAllBlacklistChecker();

        self::assertFalse($checker->isBlacklisted('a@example.com', '+381641234567'));
        self::assertFalse($checker->isBlacklisted(null, null));
    }

    public function testTheTemporaryRoleLookupGrantsNothing(): void
    {
        self::assertSame([], (new NoRolesGrantedYet())->rolesFor(new UserId('01900000-0000-7000-8000-0000000000aa')));
    }
}
