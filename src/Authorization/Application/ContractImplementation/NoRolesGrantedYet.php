<?php

declare(strict_types=1);

namespace App\Authorization\Application\ContractImplementation;

use App\Authorization\Contract\RoleLookup;
use App\SharedKernel\Contract\UserId;

/**
 * Temporary implementation until the Authorization module stores role assignments: no roles are granted, so the
 * Authentication module falls back to the role the account registered with.
 */
final readonly class NoRolesGrantedYet implements RoleLookup
{
    public function rolesFor(UserId $userId): array
    {
        return [];
    }
}
