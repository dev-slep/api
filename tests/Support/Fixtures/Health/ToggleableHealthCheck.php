<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Health;

use App\SharedKernel\Infrastructure\Health\HealthCheck;
use App\SharedKernel\Infrastructure\Health\HealthResult;

/**
 * Test-only readiness probe that fails on demand (registered under when@test).
 */
final class ToggleableHealthCheck implements HealthCheck
{
    public bool $failing = false;

    public function name(): string
    {
        return 'fixture';
    }

    public function check(): HealthResult
    {
        return $this->failing ? HealthResult::unhealthy() : HealthResult::healthy();
    }
}
