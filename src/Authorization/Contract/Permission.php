<?php

declare(strict_types=1);

namespace App\Authorization\Contract;

/**
 * Everything a user can be allowed to do, by name. Authorization owns the list and says which roles hold each entry
 * (`Application\Service\PermissionCatalogue`). A module that builds an endpoint adds its cases here in the same change.
 *
 * Ownership rules ("only the owner of this request") are not permissions: they stay in the module's domain.
 */
enum Permission: string
{
    case ReadAuditLog = 'audit.read';
}
