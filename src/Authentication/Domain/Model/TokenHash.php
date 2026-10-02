<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\InvalidValue;

/**
 * SHA-256 (hex) of a token: what the database stores.
 */
final readonly class TokenHash
{
    private string $value;

    public function __construct(string $value)
    {
        $normalised = strtolower($value);
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $normalised)) {
            throw InvalidValue::because('The token hash is not valid.');
        }

        $this->value = $normalised;
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
