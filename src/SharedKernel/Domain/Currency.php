<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

final readonly class Currency
{
    public function __construct(public string $code)
    {
        if (1 !== preg_match('/^[A-Z]{3}$/D', $code)) {
            throw InvalidCurrency::code($code);
        }
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
