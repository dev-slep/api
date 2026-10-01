<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Time;

use App\SharedKernel\Infrastructure\Time\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    public function testNowIsCurrentAndInUtc(): void
    {
        $before = new DateTimeImmutable();
        $now = (new SystemClock())->now();
        $after = new DateTimeImmutable();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }
}
