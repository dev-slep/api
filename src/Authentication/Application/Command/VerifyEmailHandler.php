<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;

final readonly class VerifyEmailHandler implements CommandHandler
{
    public function __construct(
        private OneTimeTokenRepository $oneTimeTokens,
        private UserAccountRepository $accounts,
        private TokenHasher $hasher,
        private Clock $clock,
    ) {
    }

    public function __invoke(VerifyEmail $command): void
    {
        $token = $this->oneTimeTokens->findByHash(OneTimeTokenPurpose::EmailVerification, $this->hasher->hash($command->token));
        if (null === $token) {
            throw AuthenticationProblem::verificationTokenInvalid();
        }

        $now = $this->clock->now();
        $token->consume($now);

        $account = $this->accounts->findById($token->accountId()) ?? throw AuthenticationProblem::verificationTokenInvalid();
        $account->verifyEmail($now);

        $this->oneTimeTokens->save($token);
        $this->accounts->save($account);
    }
}
