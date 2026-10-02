<?php

declare(strict_types=1);

namespace App\Penalty\Application\ContractImplementation;

use App\Penalty\Contract\BlacklistChecker;

/**
 * Temporary implementation until the Penalty module keeps a real blacklist (business-logic §9): nothing is blocked.
 */
final readonly class AllowAllBlacklistChecker implements BlacklistChecker
{
    public function isBlacklisted(?string $email, ?string $phone): bool
    {
        return false;
    }
}
