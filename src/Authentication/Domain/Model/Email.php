<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\InvalidValue;

use const FILTER_VALIDATE_EMAIL;

use function mb_strlen;

use Stringable;

final readonly class Email implements Stringable
{
    private const int MAX_LENGTH = 254;

    private string $value;

    public function __construct(string $value)
    {
        $normalised = mb_strtolower(trim($value));

        if ('' === $normalised || mb_strlen($normalised) > self::MAX_LENGTH || false === filter_var($normalised, FILTER_VALIDATE_EMAIL)) {
            throw InvalidValue::because('The email address is not valid.');
        }

        $this->value = $normalised;
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
