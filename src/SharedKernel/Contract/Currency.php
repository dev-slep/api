<?php

declare(strict_types=1);

namespace App\SharedKernel\Contract;

use InvalidArgumentException;

use function sprintf;

use Stringable;

/**
 * ISO 4217 currency code as exposed in contracts. Deliberately separate from the Domain
 * `Currency`: contract types never depend on Domain types.
 */
final readonly class Currency implements Stringable
{
    public function __construct(public string $code)
    {
        if (1 !== preg_match('/^[A-Z]{3}$/D', $code)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid ISO 4217 currency code.', $code));
        }
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
