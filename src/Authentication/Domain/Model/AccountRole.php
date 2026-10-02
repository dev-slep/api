<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

/**
 * The role an account registered with. Authorization owns the access decisions; this is only the role asked for
 * at registration (DRIVER or TOWER) or given when an admin is created.
 */
enum AccountRole: string
{
    case Driver = 'DRIVER';
    case Tower = 'TOWER';
    case Admin = 'ADMIN';

    public function isSelfRegistrable(): bool
    {
        return self::Admin !== $this;
    }

    public function securityRole(): string
    {
        return 'ROLE_'.$this->value;
    }
}
