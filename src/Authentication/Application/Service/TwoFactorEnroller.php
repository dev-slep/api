<?php

declare(strict_types=1);

namespace App\Authentication\Application\Service;

use App\Authentication\Application\Command\Result\TwoFactorEnrolment;
use App\Authentication\Application\Port\TotpProvisioner;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\TwoFactorSecret;
use App\Authentication\Domain\Model\TwoFactorSecretId;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Policy\SecretEncrypter;
use App\Authentication\Domain\Repository\TwoFactorSecretRepository;
use App\SharedKernel\Domain\IdGenerator;

/**
 * Starts (or restarts) the second-factor enrolment of an admin: a new unconfirmed secret replaces an earlier unconfirmed one.
 */
final readonly class TwoFactorEnroller
{
    public function __construct(
        private TwoFactorSecretRepository $secrets,
        private TotpProvisioner $totp,
        private SecretEncrypter $encrypter,
        private IdGenerator $ids,
    ) {
    }

    /**
     * @throws AuthenticationProblem two-factor-already-enrolled when a confirmed secret exists
     */
    public function start(UserAccount $account): TwoFactorEnrolment
    {
        $existing = $this->secrets->findByAccount($account->id());
        if (null !== $existing) {
            if ($existing->isConfirmed()) {
                throw AuthenticationProblem::twoFactorAlreadyEnrolled();
            }
            $this->secrets->delete($existing);
        }

        $secret = $this->totp->generateSecret();
        $this->secrets->save(TwoFactorSecret::enrol(new TwoFactorSecretId($this->ids->generate()), $account->id(), $this->encrypter->encrypt($secret)));

        return new TwoFactorEnrolment($secret, $this->totp->provisioningUri($secret, $account->email()));
    }
}
