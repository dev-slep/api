<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\Authentication\Application\Port\AccessTokenIssuer;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Application\Port\IssuedAccessToken;
use App\Authentication\Domain\Model\AccountId;

use function sprintf;

/**
 * "access|<account id>|<roles>|<method>", so tests can read what was issued without a real signature.
 */
final class FakeAccessTokenIssuer implements AccessTokenIssuer
{
    public function issue(AccountId $accountId, array $roles, AuthenticationMethod $method): IssuedAccessToken
    {
        return new IssuedAccessToken(sprintf('access|%s|%s|%s', $accountId->toString(), implode(',', $roles), $method->value), 900);
    }
}
