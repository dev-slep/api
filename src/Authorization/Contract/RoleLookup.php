<?php

declare(strict_types=1);

namespace App\Authorization\Contract;

use App\SharedKernel\Contract\UserId;

interface RoleLookup
{
    /**
     * @return list<string> the security roles granted to the user ("ROLE_DRIVER", "ROLE_TOWER", "ROLE_ADMIN"),
     *                      empty when none was granted (yet)
     */
    public function rolesFor(UserId $userId): array;
}
