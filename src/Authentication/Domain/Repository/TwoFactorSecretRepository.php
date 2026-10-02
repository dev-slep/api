<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Repository;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\TwoFactorSecret;

interface TwoFactorSecretRepository
{
    public function findByAccount(AccountId $accountId): ?TwoFactorSecret;

    /**
     * Saves the secret (one per account) and registers it for event publishing.
     */
    public function save(TwoFactorSecret $secret): void;

    /**
     * Removes an unconfirmed secret so a new enrolment can start.
     */
    public function delete(TwoFactorSecret $secret): void;
}
