<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Service\SecurityEvents;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;

final readonly class LogoutHandler implements CommandHandler
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private TokenHasher $hasher,
        private SecurityEvents $events,
        private Clock $clock,
    ) {
    }

    /**
     * Idempotent: an unknown token is ignored, so a retried logout does not fail.
     */
    public function __invoke(Logout $command): void
    {
        $token = $this->refreshTokens->findByHash($this->hasher->hash($command->refreshToken));
        if (null === $token) {
            return;
        }

        $now = $this->clock->now();
        if ($command->allDevices) {
            $this->refreshTokens->revokeAllForAccount($token->accountId(), $now);
        } else {
            $this->refreshTokens->revokeFamily($token->familyId(), $now);
        }

        $this->events->loggedOut($token->accountId(), $command->allDevices);
    }
}
