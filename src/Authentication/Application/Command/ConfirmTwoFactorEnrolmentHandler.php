<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Command\Result\RecoveryCodes;
use App\Authentication\Application\Port\TotpVerifier;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Policy\SecretEncrypter;
use App\Authentication\Domain\Policy\TokenGenerator;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\TwoFactorSecretRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;

final readonly class ConfirmTwoFactorEnrolmentHandler implements CommandHandler
{
    public const int RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private TwoFactorSecretRepository $secrets,
        private TotpVerifier $totp,
        private SecretEncrypter $encrypter,
        private TokenGenerator $generator,
        private TokenHasher $hasher,
        private Clock $clock,
    ) {
    }

    /**
     * Confirms the enrolment with a first valid code.
     *
     * @return RecoveryCodes the recovery codes, shown to the admin exactly once
     */
    public function __invoke(ConfirmTwoFactorEnrolment $command): RecoveryCodes
    {
        $secret = $this->secrets->findByAccount(new AccountId($command->accountId));
        if (null === $secret) {
            throw AuthenticationProblem::twoFactorNotEnrolled();
        }
        if ($secret->isConfirmed()) {
            throw AuthenticationProblem::twoFactorAlreadyEnrolled();
        }

        $now = $this->clock->now();
        $step = $this->totp->verify($this->encrypter->decrypt($secret->encryptedSecret()), $command->code, $now)
            ?? throw AuthenticationProblem::twoFactorInvalid();

        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; ++$i) {
            $codes[] = $this->generator->recoveryCode();
        }
        $secret->confirm(array_map(fn (string $code) => $this->hasher->hash($code), $codes), $step, $now);
        $this->secrets->save($secret);

        return new RecoveryCodes($codes);
    }
}
