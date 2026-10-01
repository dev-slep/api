<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

/**
 * The shared HTTP behaviour applies to `/api/*` only (health endpoints and tooling are left alone).
 */
final readonly class ApiPath
{
    public static function matches(string $path): bool
    {
        return '/api' === $path || str_starts_with($path, '/api/');
    }
}
