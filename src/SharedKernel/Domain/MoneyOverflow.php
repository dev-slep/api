<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

use function sprintf;

final class MoneyOverflow extends DomainException
{
    public static function whileCalculating(string $operation): self
    {
        return new self(sprintf('Money amount overflowed while calculating: %s.', $operation));
    }
}
