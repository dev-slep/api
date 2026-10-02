<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

/**
 * What a verified access token says about its holder.
 */
final readonly class AuthenticatedPrincipal
{
    /**
     * @param non-empty-string $accountId
     * @param list<string>     $roles
     */
    public function __construct(
        public string $accountId,
        public array $roles,
        public AuthenticationMethod $method,
    ) {
    }

    public function isTwoFactorPending(): bool
    {
        return AuthenticationMethod::PendingTwoFactor === $this->method;
    }

    /**
     * True when no second factor was needed (non-admin) or it has been passed.
     */
    public function isTwoFactorVerified(): bool
    {
        return AuthenticationMethod::PasswordAndOtp === $this->method;
    }
}
