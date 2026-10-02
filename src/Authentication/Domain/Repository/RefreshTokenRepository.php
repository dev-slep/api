<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Repository;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\RefreshToken;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\Authentication\Domain\Model\TokenHash;
use DateTimeImmutable;

interface RefreshTokenRepository
{
    public function findByHash(TokenHash $hash): ?RefreshToken;

    /**
     * @throws AuthenticationProblem token-invalid when the same token was changed concurrently (optimistic lock)
     */
    public function save(RefreshToken $token): void;

    /**
     * Revokes every token of the family that is not revoked yet.
     */
    public function revokeFamily(TokenFamilyId $familyId, DateTimeImmutable $now): void;

    /**
     * Revokes every token of the account that is not revoked yet.
     */
    public function revokeAllForAccount(AccountId $accountId, DateTimeImmutable $now): void;

    /**
     * Deletes tokens that expired before the given moment.
     *
     * @return int the number of deleted tokens
     */
    public function deleteExpiredBefore(DateTimeImmutable $moment): int;
}
