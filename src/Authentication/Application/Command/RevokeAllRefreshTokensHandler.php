<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;

final readonly class RevokeAllRefreshTokensHandler implements CommandHandler
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private Clock $clock,
    ) {
    }

    public function __invoke(RevokeAllRefreshTokens $command): void
    {
        $this->refreshTokens->revokeAllForAccount(new AccountId($command->accountId), $this->clock->now());
    }
}
