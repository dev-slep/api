<?php

declare(strict_types=1);

namespace App\SharedKernel\Contract;

use InvalidArgumentException;

use function sprintf;

use Stringable;

/**
 * Identifier of a user account as seen by other modules (a UUID).
 */
final readonly class UserId implements Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $normalised = strtolower($value);

        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $normalised)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid user id (UUID expected).', $value));
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
