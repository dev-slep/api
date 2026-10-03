<?php

declare(strict_types=1);

namespace App\Authorization\Domain\Model;

use App\SharedKernel\Domain\EntityId;

/**
 * The user (account) a role is assigned to.
 */
final readonly class AssignedUserId extends EntityId
{
}
