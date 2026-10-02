<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Application\Service\SecurityEvents;
use App\Authentication\Application\Service\SessionFactory;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\RefreshTokenStatus;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;

final readonly class RefreshTokensHandler implements CommandHandler
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private UserAccountRepository $accounts,
        private TokenHasher $hasher,
        private SessionFactory $sessions,
        private SecurityEvents $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RefreshTokens $command): AuthenticationResult
    {
        $token = $this->refreshTokens->findByHash($this->hasher->hash($command->refreshToken));
        if (null === $token) {
            return AuthenticationResult::failed(AuthenticationProblem::tokenInvalid());
        }

        $now = $this->clock->now();
        switch ($token->status($now)) {
            case RefreshTokenStatus::Rotated:
                // An old token was presented again: someone holds a copy, so the whole family is cut off
                $this->refreshTokens->revokeFamily($token->familyId(), $now);
                $this->events->refreshTokenReused($token->accountId(), $token->familyId(), $command->context);

                return AuthenticationResult::failed(AuthenticationProblem::tokenRevoked());
            case RefreshTokenStatus::Revoked:
                return AuthenticationResult::failed(AuthenticationProblem::tokenRevoked());
            case RefreshTokenStatus::Expired:
                return AuthenticationResult::failed(AuthenticationProblem::tokenExpired());
            case RefreshTokenStatus::Usable:
                break;
        }

        $account = $this->accounts->findById($token->accountId());
        if (null === $account) {
            return AuthenticationResult::failed(AuthenticationProblem::tokenInvalid());
        }
        try {
            $account->assertCanLogIn();
        } catch (AuthenticationProblem $refusal) {
            $this->refreshTokens->revokeFamily($token->familyId(), $now);

            return AuthenticationResult::failed($refusal);
        }

        try {
            return AuthenticationResult::success($this->sessions->continueFamily($token, $account));
        } catch (AuthenticationProblem $concurrentUse) {
            return AuthenticationResult::failed($concurrentUse);
        }
    }
}
