<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Exception;

use App\SharedKernel\Domain\DomainException;

/**
 * A value object was built from input that breaks its rules (reported as a generic 422 domain error).
 */
final class InvalidValue extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
