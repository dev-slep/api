<?php

declare(strict_types=1);

namespace App\Authorization\Domain\Model;

use App\Authorization\Domain\Exception\UnknownRole;

/**
 * The roles a user can hold. A user has exactly one.
 */
enum Role: string
{
    case Driver = 'DRIVER';
    case Tower = 'TOWER';
    case Admin = 'ADMIN';

    /**
     * @throws UnknownRole
     */
    public static function fromName(string $name): self
    {
        return self::tryFrom($name) ?? throw UnknownRole::named($name);
    }

    /**
     * The Symfony security role carried in access tokens, e.g. "ROLE_DRIVER".
     */
    public function securityRole(): string
    {
        return 'ROLE_'.$this->value;
    }
}
