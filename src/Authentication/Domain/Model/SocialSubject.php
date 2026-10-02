<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\InvalidValue;

use function mb_strlen;

use Stringable;

/**
 * The provider's stable identifier of a person (the `sub` claim of the ID token).
 */
final readonly class SocialSubject implements Stringable
{
    private const int MAX_LENGTH = 255;

    public function __construct(private string $value)
    {
        if ('' === $value || mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::because('The social identity subject is not valid.');
        }
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
