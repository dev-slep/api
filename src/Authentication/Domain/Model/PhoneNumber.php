<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\InvalidValue;
use Stringable;

/**
 * A phone number in international form (digits with an optional leading "+", 7 to 15 digits).
 * It is stored as typed apart from separators, and is not verified (backend-specification §7.1).
 */
final readonly class PhoneNumber implements Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $cleaned = preg_replace('/[\s().\-]/', '', trim($value)) ?? '';

        if (1 !== preg_match('/^\+?[0-9]{7,15}$/D', $cleaned)) {
            throw InvalidValue::because('The phone number is not valid.');
        }

        $this->value = $cleaned;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
