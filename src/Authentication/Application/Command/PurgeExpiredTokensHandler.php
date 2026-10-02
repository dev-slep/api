<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Port\AuthenticationSettings;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;

final readonly class PurgeExpiredTokensHandler implements CommandHandler
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private OneTimeTokenRepository $oneTimeTokens,
        private AuthenticationSettings $settings,
        private Clock $clock,
    ) {
    }

    /**
     * @return int the number of tokens deleted
     */
    public function __invoke(PurgeExpiredTokens $command): int
    {
        $cutoff = $this->clock->now()->sub($this->settings->expiredTokenRetention());

        return $this->refreshTokens->deleteExpiredBefore($cutoff) + $this->oneTimeTokens->deleteExpiredBefore($cutoff);
    }
}
