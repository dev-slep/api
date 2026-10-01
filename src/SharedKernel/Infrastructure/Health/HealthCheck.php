<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Health;

/**
 * One readiness probe of an infrastructure dependency. Implementations are tagged `app.health_check` automatically.
 */
interface HealthCheck
{
    /** Short stable name used as the key in the readiness response, e.g. "database". */
    public function name(): string;

    /**
     * Must never throw: a failing dependency is reported as an unhealthy result.
     */
    public function check(): HealthResult;
}
