<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;

final readonly class BanAccountHandler implements CommandHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private RefreshTokenRepository $refreshTokens,
        private Clock $clock,
    ) {
    }

    /**
     * Idempotent. An unknown account is ignored: Penalty may ban users this module never saw.
     */
    public function __invoke(BanAccount $command): void
    {
        $id = new AccountId($command->accountId);
        $account = $this->accounts->findById($id);
        if (null !== $account) {
            $account->ban();
            $this->accounts->save($account);
        }

        $this->refreshTokens->revokeAllForAccount($id, $this->clock->now());
    }
}
