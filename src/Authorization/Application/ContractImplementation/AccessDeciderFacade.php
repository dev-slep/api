<?php

declare(strict_types=1);

namespace App\Authorization\Application\ContractImplementation;

use App\Authorization\Application\Service\PermissionCatalogue;
use App\Authorization\Contract\AccessDecider;
use App\Authorization\Contract\Permission;
use App\Authorization\Contract\RoleLookup;
use App\Authorization\Domain\Model\Role;
use App\SharedKernel\Contract\UserId;

use function in_array;

final readonly class AccessDeciderFacade implements AccessDecider
{
    public function __construct(
        private RoleLookup $roles,
        private PermissionCatalogue $catalogue,
    ) {
    }

    public function isGranted(UserId $userId, Permission $permission): bool
    {
        return $this->allows($this->roles->rolesFor($userId), $permission);
    }

    public function allows(array $securityRoles, Permission $permission): bool
    {
        $roles = [];
        foreach (Role::cases() as $role) {
            if (in_array($role->securityRole(), $securityRoles, true)) {
                $roles[] = $role;
            }
        }

        return $this->catalogue->policy()->isHeldBy($permission->value, $roles);
    }
}
