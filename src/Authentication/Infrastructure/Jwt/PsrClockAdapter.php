<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Jwt;

use App\SharedKernel\Domain\Clock;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Lets the JWT library read time from the application's {@see Clock} (frozen in tests).
 */
final readonly class PsrClockAdapter implements ClockInterface
{
    public function __construct(private Clock $clock)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
