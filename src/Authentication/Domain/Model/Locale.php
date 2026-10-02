<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\InvalidValue;
use Stringable;

/**
 * The language of an account's emails (e.g. "sr_Latn", "en").
 */
final readonly class Locale implements Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        if (1 !== preg_match('/^[a-z]{2}(_[A-Za-z]{2,4})?$/D', $value)) {
            throw InvalidValue::because('The locale is not valid.');
        }

        $this->value = $value;
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
