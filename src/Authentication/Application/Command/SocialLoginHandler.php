<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Application\Port\AuthEmailSender;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Application\Port\AuthenticationSettings;
use App\Authentication\Application\Port\SocialIdentityVerifier;
use App\Authentication\Application\Port\VerifiedSocialIdentity;
use App\Authentication\Application\Service\SecurityEvents;
use App\Authentication\Application\Service\SessionFactory;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Exception\InvalidValue;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenId;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\PhoneNumber;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Policy\TokenGenerator;
use App\Authentication\Domain\Policy\TokenHasher;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\Penalty\Contract\BlacklistChecker;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;
use DateTimeImmutable;

final readonly class SocialLoginHandler implements CommandHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private OneTimeTokenRepository $oneTimeTokens,
        private RefreshTokenRepository $refreshTokens,
        private SocialIdentityVerifier $verifier,
        private BlacklistChecker $blacklist,
        private TokenGenerator $generator,
        private TokenHasher $tokenHasher,
        private AuthEmailSender $mailer,
        private AuthenticationSettings $settings,
        private SessionFactory $sessions,
        private SecurityEvents $events,
        private Clock $clock,
        private IdGenerator $ids,
    ) {
    }

    public function __invoke(SocialLogin $command): AuthenticationResult
    {
        $provider = SocialProvider::tryFrom($command->provider) ?? throw InvalidValue::because('The social provider is not supported.');
        $verified = $this->verifier->verify($provider, $command->idToken);
        $now = $this->clock->now();

        $account = $this->accounts->findBySocialIdentity($verified->identity);
        if (null === $account) {
            $existing = $this->accounts->findByEmail($verified->email);
            if (null === $existing) {
                $account = $this->register($command, $verified);
            } else {
                $account = $this->link($existing, $verified, $now);
            }
        }

        if (AccountRole::Admin === $account->role()) {
            throw AuthenticationProblem::adminSocialLoginForbidden();
        }

        try {
            $account->assertCanLogIn();
        } catch (AuthenticationProblem $refusal) {
            $this->events->loginFailed($account->id(), $refusal->problemSlug(), $command->context);

            return AuthenticationResult::failed($refusal);
        }

        $tokens = $this->sessions->start($account, AuthenticationMethod::Social);
        $this->events->loggedIn($account->id(), 'social:'.$provider->value, $command->context);

        return AuthenticationResult::success($tokens);
    }

    private function register(SocialLogin $command, VerifiedSocialIdentity $verified): UserAccount
    {
        if (null === $command->role) {
            throw AuthenticationProblem::socialRoleRequired();
        }
        $role = AccountRole::tryFrom(strtoupper($command->role)) ?? throw InvalidValue::because('The role is not valid.');
        $phone = null === $command->phone ? null : new PhoneNumber($command->phone);
        $locale = new Locale($command->locale);

        if ($this->blacklist->isBlacklisted($verified->email->toString(), $phone?->toString())) {
            throw AuthenticationProblem::contactBlacklisted();
        }

        $now = $this->clock->now();
        $account = UserAccount::registerWithSocialIdentity(
            new AccountId($this->ids->generate()),
            $verified->email,
            $role,
            $phone,
            $locale,
            $verified->identity,
            $verified->emailVerified,
            $now,
        );
        $this->accounts->save($account);

        if (!$verified->emailVerified) {
            $raw = $this->generator->generate();
            $this->oneTimeTokens->save(OneTimeToken::issue(
                new OneTimeTokenId($this->ids->generate()),
                $account->id(),
                OneTimeTokenPurpose::EmailVerification,
                $this->tokenHasher->hash($raw->reveal()),
                $now,
                $this->settings->emailVerificationLifetime(),
            ));
            $this->mailer->sendEmailVerification($verified->email, $locale, $raw);
        }

        return $account;
    }

    /**
     * Attaches the social identity to the account that already uses this email. Only a provider-confirmed
     * email may do this: otherwise anyone could take over an account by claiming its address. An account whose
     * email was never confirmed may have been registered by someone else, so its password, sessions and pending
     * tokens are discarded: the provider has just proven who owns the address.
     */
    private function link(UserAccount $existing, VerifiedSocialIdentity $verified, DateTimeImmutable $now): UserAccount
    {
        if (AccountRole::Admin === $existing->role()) {
            throw AuthenticationProblem::adminSocialLoginForbidden();
        }
        if (!$verified->emailVerified) {
            throw AuthenticationProblem::emailAlreadyRegistered();
        }

        if (!$existing->isEmailVerified()) {
            $existing->discardUnconfirmedPassword();
            $this->refreshTokens->revokeAllForAccount($existing->id(), $now);
            $this->oneTimeTokens->invalidateAllFor($existing->id(), OneTimeTokenPurpose::EmailVerification, $now);
            $this->oneTimeTokens->invalidateAllFor($existing->id(), OneTimeTokenPurpose::PasswordReset, $now);
        }

        $existing->linkSocialIdentity($verified->identity, true, $now);
        $this->accounts->save($existing);

        return $existing;
    }
}
