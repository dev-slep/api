<?php

declare(strict_types=1);

namespace App\Authentication\Contract;

use App\SharedKernel\Contract\UserId;
use LogicException;

/**
 * The user behind the current HTTP request, as established by the access token.
 */
interface CurrentUser
{
    public function isAuthenticated(): bool;

    /**
     * @throws LogicException when nobody is authenticated
     */
    public function id(): UserId;

    /**
     * @return list<string> security roles such as "ROLE_DRIVER"; empty when nobody is authenticated
     */
    public function roles(): array;

    /**
     * True when the token was issued after a successful second factor (admins).
     */
    public function isTwoFactorVerified(): bool;
}
