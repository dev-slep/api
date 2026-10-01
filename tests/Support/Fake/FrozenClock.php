<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\SharedKernel\Domain\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $now = '2026-01-01T12:00:00+00:00')
    {
        $this->now = new DateTimeImmutable($now)->setTimezone(new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now->setTimezone(new DateTimeZone('UTC'));
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->modify($interval);
    }
}
