<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Repository;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\TokenHash;
use DateTimeImmutable;

interface OneTimeTokenRepository
{
    public function findByHash(OneTimeTokenPurpose $purpose, TokenHash $hash): ?OneTimeToken;

    public function save(OneTimeToken $token): void;

    /**
     * Invalidates the account's tokens of that purpose that are still usable (a new token replaces them).
     */
    public function invalidateAllFor(AccountId $accountId, OneTimeTokenPurpose $purpose, DateTimeImmutable $now): void;

    /**
     * Deletes tokens that expired before the given moment.
     *
     * @return int the number of deleted tokens
     */
    public function deleteExpiredBefore(DateTimeImmutable $moment): int;
}
