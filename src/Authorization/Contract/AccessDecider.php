<?php

declare(strict_types=1);

namespace App\Authorization\Contract;

use App\SharedKernel\Contract\UserId;

interface AccessDecider
{
    /**
     * Whether the role stored for the user holds the permission. For handlers and workers, where no access token exists.
     * A user without a role is refused.
     */
    public function isGranted(UserId $userId, Permission $permission): bool;

    /**
     * Whether any of the given security roles (such as "ROLE_ADMIN", as carried in an access token) holds the permission.
     * Unknown roles are ignored. Pure: nothing is read from the database.
     *
     * @param list<string> $securityRoles
     */
    public function allows(array $securityRoles, Permission $permission): bool;
}
