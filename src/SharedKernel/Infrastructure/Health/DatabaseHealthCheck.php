<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Health;

use Doctrine\DBAL\Connection;
use Throwable;

final readonly class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(private Connection $connection)
    {
    }

    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthResult
    {
        try {
            $this->connection->fetchOne('SELECT 1');
        } catch (Throwable) {
            return HealthResult::unhealthy();
        }

        return HealthResult::healthy();
    }
}
