<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Port\AuthEmailSender;
use App\Authentication\Application\Port\AuthenticationSettings;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Exception\InvalidValue;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenId;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\PhoneNumber;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Policy\PasswordHasher;
use App\Authentication\Domain\Policy\PasswordPolicy;
use App\Authentication\Domain\Policy\TokenGenerator;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\Penalty\Contract\BlacklistChecker;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;

final readonly class RegisterUserHandler implements CommandHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private OneTimeTokenRepository $oneTimeTokens,
        private PasswordHasher $hasher,
        private PasswordPolicy $passwordPolicy,
        private BlacklistChecker $blacklist,
        private TokenGenerator $generator,
        private TokenHasher $tokenHasher,
        private AuthEmailSender $mailer,
        private AuthenticationSettings $settings,
        private Clock $clock,
        private IdGenerator $ids,
    ) {
    }

    public function __invoke(RegisterUser $command): void
    {
        $email = new Email($command->email);
        $password = new PlainPassword($command->password);
        $role = AccountRole::tryFrom(strtoupper($command->role)) ?? throw InvalidValue::because('The role is not valid.');
        $phone = null === $command->phone ? null : new PhoneNumber($command->phone);
        $locale = new Locale($command->locale);

        $this->passwordPolicy->assertAcceptable($password, $email);

        if ($this->blacklist->isBlacklisted($email->toString(), $phone?->toString())) {
            throw AuthenticationProblem::contactBlacklisted();
        }
        if (null !== $this->accounts->findByEmail($email)) {
            throw AuthenticationProblem::emailAlreadyRegistered();
        }

        $now = $this->clock->now();
        $account = UserAccount::registerWithPassword(new AccountId($this->ids->generate()), $email, $this->hasher->hash($password), $role, $phone, $locale, $now);
        $this->accounts->save($account);

        $raw = $this->generator->generate();
        $this->oneTimeTokens->save(OneTimeToken::issue(
            new OneTimeTokenId($this->ids->generate()),
            $account->id(),
            OneTimeTokenPurpose::EmailVerification,
            $this->tokenHasher->hash($raw->reveal()),
            $now,
            $this->settings->emailVerificationLifetime(),
        ));
        $this->mailer->sendEmailVerification($email, $locale, $raw);
    }
}
