<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command\Result;

use App\Authentication\Domain\Exception\AuthenticationProblem;

/**
 * The outcome of a login-like command. Refusals that must leave a trace (a failed login records an event) are
 * returned, not thrown, so the command's transaction commits; the HTTP layer turns them into problem responses
 * with {@see self::orThrow()}.
 */
final readonly class AuthenticationResult
{
    private function __construct(
        public ?TokenPair $tokens,
        public ?string $pendingAccessToken,
        public ?int $pendingExpiresInSeconds,
        public ?AuthenticationProblem $failure,
    ) {
    }

    public static function success(TokenPair $tokens): self
    {
        return new self($tokens, null, null, null);
    }

    /**
     * The password was right but an admin still has to pass the second factor.
     */
    public static function twoFactorRequired(string $pendingAccessToken, int $expiresInSeconds): self
    {
        return new self(null, $pendingAccessToken, $expiresInSeconds, null);
    }

    public static function failed(AuthenticationProblem $failure): self
    {
        return new self(null, null, null, $failure);
    }

    public function isSuccess(): bool
    {
        return null !== $this->tokens;
    }

    public function isTwoFactorRequired(): bool
    {
        return null !== $this->pendingAccessToken;
    }

    /**
     * @throws AuthenticationProblem when the command was refused
     */
    public function orThrow(): self
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return $this;
    }
}
