<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Application\Port\TotpVerifier;
use App\Authentication\Application\Service\SecurityEvents;
use App\Authentication\Application\Service\SessionFactory;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\TwoFactorSecret;
use App\Authentication\Domain\Policy\SecretEncrypter;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\TwoFactorSecretRepository;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;

final readonly class VerifyTwoFactorHandler implements CommandHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private TwoFactorSecretRepository $secrets,
        private TotpVerifier $totp,
        private SecretEncrypter $encrypter,
        private TokenHasher $hasher,
        private SessionFactory $sessions,
        private SecurityEvents $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(VerifyTwoFactor $command): AuthenticationResult
    {
        $account = $this->accounts->findById(new AccountId($command->accountId));
        if (null === $account) {
            throw AuthenticationProblem::tokenInvalid();
        }
        if (AccountRole::Admin !== $account->role()) {
            throw AuthenticationProblem::adminOnly();
        }

        $secret = $this->secrets->findByAccount($account->id());
        if (null === $secret || !$secret->isConfirmed()) {
            throw AuthenticationProblem::twoFactorNotEnrolled();
        }

        try {
            $account->assertCanLogIn();
        } catch (AuthenticationProblem $refusal) {
            $this->events->loginFailed($account->id(), $refusal->problemSlug(), $command->context);

            return AuthenticationResult::failed($refusal);
        }

        $method = $this->accept($secret, $command->code);
        if (null === $method) {
            $this->events->twoFactorFailed($account->id(), $command->context);

            return AuthenticationResult::failed(AuthenticationProblem::twoFactorInvalid());
        }

        $this->secrets->save($secret);
        $this->events->twoFactorVerified($account->id(), $method, $command->context);

        $tokens = $this->sessions->start($account, AuthenticationMethod::PasswordAndOtp);
        $this->events->loggedIn($account->id(), AuthenticationMethod::PasswordAndOtp->value, $command->context);

        return AuthenticationResult::success($tokens);
    }

    /**
     * @return string|null "totp" or "recovery_code" when the code is good, null when it is not (or is a replay)
     */
    private function accept(TwoFactorSecret $secret, string $code): ?string
    {
        $step = $this->totp->verify($this->encrypter->decrypt($secret->encryptedSecret()), $code, $this->clock->now());
        if (null !== $step) {
            try {
                $secret->acceptStep($step);
            } catch (AuthenticationProblem) {
                return null;
            }

            return 'totp';
        }

        $recoveryCode = strtolower(trim($code));
        if ($secret->useRecoveryCode($this->hasher->hash($recoveryCode))) {
            return 'recovery_code';
        }

        return null;
    }
}
