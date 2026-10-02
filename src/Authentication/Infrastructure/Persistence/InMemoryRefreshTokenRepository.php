<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\RefreshToken;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use DateTimeImmutable;

/**
 * Used by application tests, which never touch the database.
 */
final class InMemoryRefreshTokenRepository implements RefreshTokenRepository
{
    /** @var array<string, RefreshToken> */
    private array $tokens = [];

    public function findByHash(TokenHash $hash): ?RefreshToken
    {
        foreach ($this->tokens as $token) {
            if ($token->hash()->equals($hash)) {
                return $token;
            }
        }

        return null;
    }

    public function save(RefreshToken $token): void
    {
        $this->tokens[$token->id()->toString()] = $token;
    }

    public function revokeFamily(TokenFamilyId $familyId, DateTimeImmutable $now): void
    {
        foreach ($this->tokens as $token) {
            if ($token->familyId()->equals($familyId)) {
                $token->revoke($now);
            }
        }
    }

    public function revokeAllForAccount(AccountId $accountId, DateTimeImmutable $now): void
    {
        foreach ($this->tokens as $token) {
            if ($token->accountId()->equals($accountId)) {
                $token->revoke($now);
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

    /**
     * @return list<RefreshToken>
     */
    public function all(): array
    {
        return array_values($this->tokens);
    }
}
