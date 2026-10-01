<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Health;

final readonly class HealthResult
{
    private function __construct(public bool $healthy)
    {
    }

    public static function healthy(): self
    {
        return new self(true);
    }

    public static function unhealthy(): self
    {
        return new self(false);
    }
}
