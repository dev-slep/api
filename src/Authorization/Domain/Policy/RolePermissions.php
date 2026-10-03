<?php

declare(strict_types=1);

namespace App\Authorization\Domain\Policy;

use App\Authorization\Domain\Model\Role;

use function in_array;

/**
 * Which roles hold which permission. Permissions are plain names here so that the domain stays independent of the
 * public `Permission` enum; the application layer feeds it the enum's values.
 */
final readonly class RolePermissions
{
    /**
     * @param array<string, list<Role>> $holders the roles that hold each permission, keyed by permission name
     */
    public function __construct(private array $holders)
    {
    }

    /**
     * @return list<Role>
     */
    public function rolesHolding(string $permission): array
    {
        return $this->holders[$permission] ?? [];
    }

    /**
     * @param list<Role> $roles
     */
    public function isHeldBy(string $permission, array $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, $this->rolesHolding($permission), true)) {
                return true;
            }
        }

        return false;
    }
}
