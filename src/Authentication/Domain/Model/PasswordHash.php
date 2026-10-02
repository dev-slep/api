<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\InvalidValue;
use SensitiveParameter;

final readonly class PasswordHash
{
    public function __construct(#[SensitiveParameter] private string $value)
    {
        if ('' === $value) {
            throw InvalidValue::because('The password hash must not be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
