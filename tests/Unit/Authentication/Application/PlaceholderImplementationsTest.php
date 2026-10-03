<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Application;

use App\Penalty\Application\ContractImplementation\AllowAllBlacklistChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllowAllBlacklistChecker::class)]
final class PlaceholderImplementationsTest extends TestCase
{
    public function testTheTemporaryBlacklistBlocksNothing(): void
    {
        $checker = new AllowAllBlacklistChecker();

        self::assertFalse($checker->isBlacklisted('a@example.com', '+381641234567'));
        self::assertFalse($checker->isBlacklisted(null, null));
    }
}
