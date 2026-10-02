<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Domain\DomainException;
use App\SharedKernel\Domain\ProblemType;

/**
 * An `Idempotency-Key` was sent again with a different request.
 */
final class IdempotencyKeyReused extends DomainException implements ProblemType
{
    public function __construct()
    {
        parent::__construct('This Idempotency-Key was already used for a different request.');
    }

    public function problemSlug(): string
    {
        return 'idempotency-key-reused';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
