<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

use function sprintf;

final class InvalidCurrency extends DomainException
{
    public static function code(string $code): self
    {
        return new self(sprintf('"%s" is not a valid ISO 4217 currency code (three uppercase letters).', $code));
    }
}
