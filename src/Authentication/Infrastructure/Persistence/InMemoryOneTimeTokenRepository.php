<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use DateTimeImmutable;

/**
 * Used by application tests, which never touch the database.
 */
final class InMemoryOneTimeTokenRepository implements OneTimeTokenRepository
{
    /** @var array<string, OneTimeToken> */
    private array $tokens = [];

    public function findByHash(OneTimeTokenPurpose $purpose, TokenHash $hash): ?OneTimeToken
    {
        foreach ($this->tokens as $token) {
            if ($token->purpose() === $purpose && $token->hash()->equals($hash)) {
                return $token;
            }
        }

        return null;
    }

    public function save(OneTimeToken $token): void
    {
        $this->tokens[$token->id()->toString()] = $token;
    }

    public function invalidateAllFor(AccountId $accountId, OneTimeTokenPurpose $purpose, DateTimeImmutable $now): void
    {
        foreach ($this->tokens as $token) {
            if ($token->accountId()->equals($accountId) && $token->purpose() === $purpose && null === $token->usedAt()) {
                $token->invalidate($now);
            }
        }
    }

    public function deleteExpiredBefore(DateTimeImmutable $moment): int
    {
        $deleted = 0;
        foreach ($this->tokens as $key => $token) {
            if ($token->expiresAt() < $moment) {
                unset($this->tokens[$key]);
                ++$deleted;
            }
        }

        return $deleted;
    }
}
