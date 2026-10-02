<?php

declare(strict_types=1);

namespace App\Tests\Application\Penalty;

use App\Penalty\Application\ContractImplementation\AllowAllBlacklistChecker;
use App\Penalty\Contract\BlacklistChecker;
use App\Tests\Support\ApplicationTestCase;
use App\Tests\Support\Attribute\CoversContractMethod;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AllowAllBlacklistChecker::class)]
#[CoversContractMethod(BlacklistChecker::class, 'isBlacklisted')]
final class BlacklistCheckerContractTest extends ApplicationTestCase
{
    private function checker(): BlacklistChecker
    {
        $checker = static::getContainer()->get(BlacklistChecker::class);

        return $checker;
    }

    public function testUntilPenaltyKeepsABlacklistNothingIsBlocked(): void
    {
        self::assertInstanceOf(AllowAllBlacklistChecker::class, $this->checker());
        self::assertFalse($this->checker()->isBlacklisted('a@example.com', '+381641234567'));
        self::assertFalse($this->checker()->isBlacklisted('a@example.com', null));
        self::assertFalse($this->checker()->isBlacklisted(null, '+381641234567'));
        self::assertFalse($this->checker()->isBlacklisted(null, null));
    }
}
