<?php

declare(strict_types=1);

namespace App\Authentication\Contract;

use App\Authentication\Contract\Dto\AccountView;
use App\SharedKernel\Contract\UserId;

interface AccountLookup
{
    public function findById(UserId $id): ?AccountView;

    /**
     * @param string $email compared case-insensitively
     *
     * @return AccountView|null null for an unknown or malformed email
     */
    public function findByEmail(string $email): ?AccountView;
}
