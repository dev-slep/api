<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Port\AuthEmailSender;
use App\Authentication\Application\Port\AuthenticationSettings;
use App\Authentication\Application\Port\EmailDeliveryFailed;
use App\Authentication\Domain\Exception\InvalidValue;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenId;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Policy\TokenGenerator;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;

final readonly class ResendEmailVerificationHandler implements CommandHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private OneTimeTokenRepository $oneTimeTokens,
        private TokenGenerator $generator,
        private TokenHasher $hasher,
        private AuthEmailSender $mailer,
        private AuthenticationSettings $settings,
        private Clock $clock,
        private IdGenerator $ids,
    ) {
    }

    /**
     * Always succeeds, whatever the email: the response must not reveal whether an account exists or is verified.
     */
    public function __invoke(ResendEmailVerification $command): void
    {
        try {
            $email = new Email($command->email);
        } catch (InvalidValue) {
            return;
        }

        $account = $this->accounts->findByEmail($email);
        if (null === $account || $account->isEmailVerified() || $account->isBanned()) {
            return;
        }

        $now = $this->clock->now();
        $this->oneTimeTokens->invalidateAllFor($account->id(), OneTimeTokenPurpose::EmailVerification, $now);

        $raw = $this->generator->generate();
        $this->oneTimeTokens->save(OneTimeToken::issue(
            new OneTimeTokenId($this->ids->generate()),
            $account->id(),
            OneTimeTokenPurpose::EmailVerification,
            $this->hasher->hash($raw->reveal()),
            $now,
            $this->settings->emailVerificationLifetime(),
        ));

        try {
            $this->mailer->sendEmailVerification($email, $account->locale(), $raw);
        } catch (EmailDeliveryFailed) {
            // Swallowed on purpose: a failure here would tell a caller that the account exists
        }
    }
}
