<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use DateInterval;

/**
 * Lifetimes of the module's tokens. Values come from configuration (config/services/authentication.yaml).
 */
final readonly class AuthenticationSettings
{
    public function __construct(
        private string $refreshTokenLifetime = 'P30D',
        private string $emailVerificationLifetime = 'PT24H',
        private string $passwordResetLifetime = 'PT1H',
        private string $expiredTokenRetention = 'P30D',
    ) {
    }

    public function refreshTokenLifetime(): DateInterval
    {
        return new DateInterval($this->refreshTokenLifetime);
    }

    public function emailVerificationLifetime(): DateInterval
    {
        return new DateInterval($this->emailVerificationLifetime);
    }

    public function passwordResetLifetime(): DateInterval
    {
        return new DateInterval($this->passwordResetLifetime);
    }

    /**
     * How long expired tokens are kept before the purge command deletes them.
     */
    public function expiredTokenRetention(): DateInterval
    {
        return new DateInterval($this->expiredTokenRetention);
    }
}
