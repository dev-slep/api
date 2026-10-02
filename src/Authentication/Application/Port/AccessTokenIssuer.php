<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use App\Authentication\Domain\Model\AccountId;

interface AccessTokenIssuer
{
    /**
     * @param list<string> $roles
     */
    public function issue(AccountId $accountId, array $roles, AuthenticationMethod $method): IssuedAccessToken;
}
