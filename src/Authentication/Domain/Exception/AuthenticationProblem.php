<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Exception;

use App\SharedKernel\Domain\DomainException;
use App\SharedKernel\Domain\ProblemType;

/**
 * Every refusal of the Authentication module that maps to an HTTP problem (RFC 9457).
 * The slug is also the translation key of the problem title (translations/problems+intl-icu.*.yaml).
 */
final class AuthenticationProblem extends DomainException implements ProblemType
{
    private function __construct(
        private readonly string $slug,
        private readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self('invalid-credentials', 401, 'The email or password is incorrect.');
    }

    public static function emailNotVerified(): self
    {
        return new self('email-not-verified', 403, 'The email address has not been verified yet.');
    }

    public static function accountBanned(): self
    {
        return new self('account-banned', 403, 'This account has been banned.');
    }

    public static function emailAlreadyRegistered(): self
    {
        return new self('email-already-registered', 409, 'An account with this email address already exists.');
    }

    public static function contactBlacklisted(): self
    {
        return new self('contact-blacklisted', 403, 'This email address or phone number cannot be used to register.');
    }

    public static function tokenExpired(): self
    {
        return new self('token-expired', 401, 'The token has expired.');
    }

    public static function tokenInvalid(): self
    {
        return new self('token-invalid', 401, 'The token is not valid.');
    }

    public static function tokenRevoked(): self
    {
        return new self('token-revoked', 401, 'The token has been revoked.');
    }

    public static function verificationTokenInvalid(): self
    {
        return new self('verification-token-invalid', 422, 'The link is invalid or has expired.');
    }

    public static function twoFactorRequired(): self
    {
        return new self('two-factor-required', 401, 'A second factor is required.');
    }

    public static function twoFactorInvalid(): self
    {
        return new self('two-factor-invalid', 401, 'The verification code is not valid.');
    }

    public static function twoFactorNotEnrolled(): self
    {
        return new self('two-factor-not-enrolled', 409, 'Two-factor authentication has not been set up for this account.');
    }

    public static function twoFactorAlreadyEnrolled(): self
    {
        return new self('two-factor-already-enrolled', 409, 'Two-factor authentication is already set up for this account.');
    }

    public static function adminOnly(): self
    {
        return new self('admin-only', 403, 'Only admin accounts can do this.');
    }

    public static function weakPassword(string $reason): self
    {
        return new self('weak-password', 422, $reason);
    }

    public static function adminRegistrationForbidden(): self
    {
        return new self('admin-registration-forbidden', 403, 'Admin accounts cannot be registered through the public API.');
    }

    public static function socialTokenInvalid(): self
    {
        return new self('social-token-invalid', 401, 'The sign-in token could not be verified.');
    }

    public static function socialRoleRequired(): self
    {
        return new self('social-role-required', 422, 'A role is required the first time someone signs in with a social account.');
    }

    public static function identityAlreadyLinked(): self
    {
        return new self('identity-already-linked', 409, 'This social identity is already linked to an account.');
    }

    public static function adminSocialLoginForbidden(): self
    {
        return new self('admin-social-login-forbidden', 403, 'Admin accounts cannot sign in with a social account.');
    }

    public function problemSlug(): string
    {
        return $this->slug;
    }

    public function httpStatus(): int
    {
        return $this->status;
    }
}
