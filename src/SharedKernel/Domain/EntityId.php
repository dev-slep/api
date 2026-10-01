<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

use Stringable;

/**
 * Base of every module's identifier value object (e.g. `TowRequestId`): a UUID string.
 */
abstract readonly class EntityId implements Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    private string $value;

    final public function __construct(string $value)
    {
        $normalised = strtolower($value);

        if (1 !== preg_match(self::PATTERN, $normalised)) {
            throw InvalidIdentifier::notAUuid($value);
        }

        $this->value = $normalised;
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class && $this->value === $other->value;
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
