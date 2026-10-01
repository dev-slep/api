<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

use function sprintf;

final class CurrencyMismatch extends DomainException
{
    public static function between(Currency $left, Currency $right): self
    {
        return new self(sprintf('Cannot combine %s with %s.', $left->code, $right->code));
    }
}
