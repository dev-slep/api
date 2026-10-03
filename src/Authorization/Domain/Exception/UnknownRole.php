<?php

declare(strict_types=1);

namespace App\Authorization\Domain\Exception;

use App\SharedKernel\Domain\DomainException;

use function sprintf;

/**
 * A role name that Authorization does not know: a contract error between modules, never a user mistake.
 */
final class UnknownRole extends DomainException
{
    public static function named(string $name): self
    {
        return new self(sprintf('"%s" is not a known role.', $name));
    }
}
