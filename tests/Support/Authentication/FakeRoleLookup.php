<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\Authorization\Contract\RoleLookup;
use App\SharedKernel\Contract\UserId;

final class FakeRoleLookup implements RoleLookup
{
    /** @var array<string, list<string>> */
    public array $roles = [];

    public function rolesFor(UserId $userId): array
    {
        return $this->roles[$userId->toString()] ?? [];
    }
}
