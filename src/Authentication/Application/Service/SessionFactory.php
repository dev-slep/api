<?php

declare(strict_types=1);

namespace App\Authentication\Application\Service;

use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Application\Command\Result\TokenPair;
use App\Authentication\Application\Port\AccessTokenIssuer;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Application\Port\AuthenticationSettings;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\RefreshToken;
use App\Authentication\Domain\Model\RefreshTokenId;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Policy\TokenGenerator;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\Authorization\Contract\RoleLookup;
use App\SharedKernel\Contract\UserId;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;

/**
 * Creates the tokens a successful authentication hands out: a refresh token (a new family on login, the next token
 * of the family on refresh) and a short-lived access token carrying the account's roles.
 */
final readonly class SessionFactory
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private AccessTokenIssuer $accessTokens,
        private TokenGenerator $generator,
        private TokenHasher $hasher,
        private RoleLookup $roles,
        private AuthenticationSettings $settings,
        private Clock $clock,
        private IdGenerator $ids,
    ) {
    }

    public function start(UserAccount $account, AuthenticationMethod $method): TokenPair
    {
        $raw = $this->generator->generate();
        $token = RefreshToken::issue(
            new RefreshTokenId($this->ids->generate()),
            new TokenFamilyId($this->ids->generate()),
            $account->id(),
            $this->hasher->hash($raw->reveal()),
            $this->clock->now(),
            $this->settings->refreshTokenLifetime(),
        );
        $this->refreshTokens->save($token);

        return $this->pair($account, $method, $raw->reveal());
    }

    /**
     * Replaces the refresh token with the next one of its family and issues a fresh access token.
     */
    public function continueFamily(RefreshToken $current, UserAccount $account): TokenPair
    {
        $raw = $this->generator->generate();
        $next = $current->rotate(
            new RefreshTokenId($this->ids->generate()),
            $this->hasher->hash($raw->reveal()),
            $this->clock->now(),
            $this->settings->refreshTokenLifetime(),
        );
        $this->refreshTokens->save($current);
        $this->refreshTokens->save($next);

        // A family only exists for admins after they passed the second factor
        $method = AccountRole::Admin === $account->role() ? AuthenticationMethod::PasswordAndOtp : AuthenticationMethod::Password;

        return $this->pair($account, $method, $raw->reveal());
    }

    /**
     * An admin who passed the password step gets a token without roles that only the second-factor endpoints accept.
     */
    public function pendingTwoFactor(UserAccount $account): AuthenticationResult
    {
        $issued = $this->accessTokens->issue($account->id(), [], AuthenticationMethod::PendingTwoFactor);

        return AuthenticationResult::twoFactorRequired($issued->token, $issued->expiresInSeconds);
    }

    /**
     * The roles Authorization granted, or the role the account registered with while it has granted none (before its
     * subscriber has handled the registration event, and for accounts that registered before the module existed).
     *
     * @return list<string>
     */
    public function securityRoles(UserAccount $account): array
    {
        $granted = $this->roles->rolesFor(new UserId($account->id()->toString()));

        return [] !== $granted ? $granted : [$account->role()->securityRole()];
    }

    private function pair(UserAccount $account, AuthenticationMethod $method, string $rawRefreshToken): TokenPair
    {
        $issued = $this->accessTokens->issue($account->id(), $this->securityRoles($account), $method);

        return new TokenPair($issued->token, $rawRefreshToken, $issued->expiresInSeconds);
    }
}
