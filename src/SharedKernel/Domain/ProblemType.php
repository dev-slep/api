<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

/**
 * Implemented by domain exceptions that map to a specific HTTP problem (RFC 9457).
 * Domain exceptions without it are reported as a generic 422 "domain-error".
 */
interface ProblemType
{
    /** Stable kebab-case identifier, e.g. "bid-deadline-passed"; also the translation key of the title. */
    public function problemSlug(): string;

    public function httpStatus(): int;
}
