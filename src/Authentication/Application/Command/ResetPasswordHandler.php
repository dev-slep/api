<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Domain\Policy\PasswordHasher;
use App\Authentication\Domain\Policy\PasswordPolicy;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;

final readonly class ResetPasswordHandler implements CommandHandler
{
    public function __construct(
        private OneTimeTokenRepository $oneTimeTokens,
        private UserAccountRepository $accounts,
        private RefreshTokenRepository $refreshTokens,
        private PasswordHasher $hasher,
        private PasswordPolicy $passwordPolicy,
        private TokenHasher $tokenHasher,
        private Clock $clock,
    ) {
    }

    /**
     * Sets a new password. Following the emailed link also proves the owner reads that mailbox, so the email
     * counts as verified. Every session of the account ends.
     */
    public function __invoke(ResetPassword $command): void
    {
        $token = $this->oneTimeTokens->findByHash(OneTimeTokenPurpose::PasswordReset, $this->tokenHasher->hash($command->token));
        if (null === $token) {
            throw AuthenticationProblem::verificationTokenInvalid();
        }

        $account = $this->accounts->findById($token->accountId()) ?? throw AuthenticationProblem::verificationTokenInvalid();
        $password = new PlainPassword($command->newPassword);

        // Checked before the token is used up: a weak password rolls everything back, so the link stays valid
        $this->passwordPolicy->assertAcceptable($password, $account->email());

        $now = $this->clock->now();
        $token->consume($now);
        $account->verifyEmail($now);
        $account->changePassword($this->hasher->hash($password), $now);

        $this->oneTimeTokens->save($token);
        $this->oneTimeTokens->invalidateAllFor($account->id(), OneTimeTokenPurpose::PasswordReset, $now);
        $this->accounts->save($account);
        $this->refreshTokens->revokeAllForAccount($account->id(), $now);
    }
}
