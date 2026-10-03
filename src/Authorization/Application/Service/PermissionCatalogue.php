<?php

declare(strict_types=1);

namespace App\Authorization\Application\Service;

use App\Authorization\Contract\Permission;
use App\Authorization\Domain\Model\Role;
use App\Authorization\Domain\Policy\RolePermissions;

/**
 * The registry: which roles hold each permission. Add a line here with every new `Permission` case.
 */
final readonly class PermissionCatalogue
{
    /** @var array<string, list<Role>> */
    private const array HOLDERS = [
        'audit.read' => [Role::Admin],
    ];

    public function policy(): RolePermissions
    {
        return new RolePermissions(self::HOLDERS);
    }

    /**
     * @return list<Permission> the permissions no role holds, which is always a mistake
     */
    public function unmapped(): array
    {
        $policy = $this->policy();
        $unmapped = [];
        foreach (Permission::cases() as $permission) {
            if ([] === $policy->rolesHolding($permission->value)) {
                $unmapped[] = $permission;
            }
        }

        return $unmapped;
    }
}
