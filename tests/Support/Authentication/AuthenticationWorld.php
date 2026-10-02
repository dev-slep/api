<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\Authentication\Application\Command\BanAccountHandler;
use App\Authentication\Application\Command\ConfirmTwoFactorEnrolmentHandler;
use App\Authentication\Application\Command\CreateAdminHandler;
use App\Authentication\Application\Command\LoginHandler;
use App\Authentication\Application\Command\LogoutHandler;
use App\Authentication\Application\Command\PurgeExpiredTokensHandler;
use App\Authentication\Application\Command\RefreshTokensHandler;
use App\Authentication\Application\Command\RegisterUserHandler;
use App\Authentication\Application\Command\RequestPasswordResetHandler;
use App\Authentication\Application\Command\ResendEmailVerificationHandler;
use App\Authentication\Application\Command\ResetPasswordHandler;
use App\Authentication\Application\Command\RevokeAllRefreshTokensHandler;
use App\Authentication\Application\Command\SocialLoginHandler;
use App\Authentication\Application\Command\StartTwoFactorEnrolmentHandler;
use App\Authentication\Application\Command\VerifyEmailHandler;
use App\Authentication\Application\Command\VerifyTwoFactorHandler;
use App\Authentication\Application\Port\AuthenticationSettings;
use App\Authentication\Application\Service\AuthenticationEventMapper;
use App\Authentication\Application\Service\SecurityEvents;
use App\Authentication\Application\Service\SessionFactory;
use App\Authentication\Application\Service\TwoFactorEnroller;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Policy\PasswordPolicy;
use App\Authentication\Infrastructure\Crypto\Argon2idPasswordHasher;
use App\Authentication\Infrastructure\Crypto\LibsodiumSecretEncrypter;
use App\Authentication\Infrastructure\Crypto\RandomTokenGenerator;
use App\Authentication\Infrastructure\Crypto\Sha256TokenHasher;
use App\Authentication\Infrastructure\Persistence\InMemoryOneTimeTokenRepository;
use App\Authentication\Infrastructure\Persistence\InMemoryRefreshTokenRepository;
use App\Authentication\Infrastructure\Persistence\InMemoryTwoFactorSecretRepository;
use App\Authentication\Infrastructure\Persistence\InMemoryUserAccountRepository;
use App\Authentication\Infrastructure\Totp\OtphpTotp;
use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Infrastructure\Messaging\CollectedAggregateEvents;
use App\Tests\Support\Fake\FrozenClock;
use App\Tests\Support\Fake\SequentialIdGenerator;

use function assert;

/**
 * Wires the Authentication use cases by hand with in-memory repositories and fakes, for unit tests of the handlers.
 * Real: Argon2id (cheap), token generation and hashing, TOTP, secret encryption.
 */
final class AuthenticationWorld
{
    public const string PASSWORD = 'correct horse battery';

    public FrozenClock $clock;
    public SequentialIdGenerator $ids;
    public CollectedAggregateEvents $collector;
    public RecordingEventBus $eventBus;
    public InMemoryUserAccountRepository $accounts;
    public InMemoryRefreshTokenRepository $refreshTokens;
    public InMemoryOneTimeTokenRepository $oneTimeTokens;
    public InMemoryTwoFactorSecretRepository $secrets;
    public FakeAuthEmailSender $mailer;
    public FakeSocialIdentityVerifier $social;
    public FakeBlacklistChecker $blacklist;
    public FakeRoleLookup $roles;
    public Argon2idPasswordHasher $hasher;
    public RandomTokenGenerator $generator;
    public Sha256TokenHasher $tokenHasher;
    public LibsodiumSecretEncrypter $encrypter;
    public OtphpTotp $totp;
    public PasswordPolicy $policy;
    public AuthenticationSettings $settings;
    public SessionFactory $sessions;
    public SecurityEvents $events;
    public TwoFactorEnroller $enroller;

    public function __construct()
    {
        $this->clock = new FrozenClock();
        $this->ids = new SequentialIdGenerator();
        $this->collector = new CollectedAggregateEvents();
        $this->eventBus = new RecordingEventBus();
        $this->accounts = new InMemoryUserAccountRepository($this->collector);
        $this->refreshTokens = new InMemoryRefreshTokenRepository();
        $this->oneTimeTokens = new InMemoryOneTimeTokenRepository();
        $this->secrets = new InMemoryTwoFactorSecretRepository($this->collector);
        $this->mailer = new FakeAuthEmailSender();
        $this->social = new FakeSocialIdentityVerifier();
        $this->blacklist = new FakeBlacklistChecker();
        $this->roles = new FakeRoleLookup();
        $this->hasher = new Argon2idPasswordHasher(8, 1);
        $this->generator = new RandomTokenGenerator();
        $this->tokenHasher = new Sha256TokenHasher();
        $this->encrypter = new LibsodiumSecretEncrypter(base64_encode(str_repeat('k', 32)));
        $this->totp = new OtphpTotp();
        $this->policy = new PasswordPolicy(10);
        $this->settings = new AuthenticationSettings();
        $this->events = new SecurityEvents($this->eventBus, $this->ids, $this->clock);
        $this->sessions = new SessionFactory($this->refreshTokens, new FakeAccessTokenIssuer(), $this->generator, $this->tokenHasher, $this->roles, $this->settings, $this->clock, $this->ids);
        $this->enroller = new TwoFactorEnroller($this->secrets, $this->totp, $this->encrypter, $this->ids);
    }

    public function register(): RegisterUserHandler
    {
        return new RegisterUserHandler($this->accounts, $this->oneTimeTokens, $this->hasher, $this->policy, $this->blacklist, $this->generator, $this->tokenHasher, $this->mailer, $this->settings, $this->clock, $this->ids);
    }

    public function login(): LoginHandler
    {
        return new LoginHandler($this->accounts, $this->hasher, $this->sessions, $this->events);
    }

    public function refresh(): RefreshTokensHandler
    {
        return new RefreshTokensHandler($this->refreshTokens, $this->accounts, $this->tokenHasher, $this->sessions, $this->events, $this->clock);
    }

    public function logout(): LogoutHandler
    {
        return new LogoutHandler($this->refreshTokens, $this->tokenHasher, $this->events, $this->clock);
    }

    public function verifyEmail(): VerifyEmailHandler
    {
        return new VerifyEmailHandler($this->oneTimeTokens, $this->accounts, $this->tokenHasher, $this->clock);
    }

    public function resendVerification(): ResendEmailVerificationHandler
    {
        return new ResendEmailVerificationHandler($this->accounts, $this->oneTimeTokens, $this->generator, $this->tokenHasher, $this->mailer, $this->settings, $this->clock, $this->ids);
    }

    public function requestReset(): RequestPasswordResetHandler
    {
        return new RequestPasswordResetHandler($this->accounts, $this->oneTimeTokens, $this->generator, $this->tokenHasher, $this->mailer, $this->settings, $this->events, $this->clock, $this->ids);
    }

    public function resetPassword(): ResetPasswordHandler
    {
        return new ResetPasswordHandler($this->oneTimeTokens, $this->accounts, $this->refreshTokens, $this->hasher, $this->policy, $this->tokenHasher, $this->clock);
    }

    public function socialLogin(): SocialLoginHandler
    {
        return new SocialLoginHandler($this->accounts, $this->oneTimeTokens, $this->refreshTokens, $this->social, $this->blacklist, $this->generator, $this->tokenHasher, $this->mailer, $this->settings, $this->sessions, $this->events, $this->clock, $this->ids);
    }

    public function startEnrolment(): StartTwoFactorEnrolmentHandler
    {
        return new StartTwoFactorEnrolmentHandler($this->accounts, $this->enroller);
    }

    public function confirmEnrolment(): ConfirmTwoFactorEnrolmentHandler
    {
        return new ConfirmTwoFactorEnrolmentHandler($this->secrets, $this->totp, $this->encrypter, $this->generator, $this->tokenHasher, $this->clock);
    }

    public function verifyTwoFactor(): VerifyTwoFactorHandler
    {
        return new VerifyTwoFactorHandler($this->accounts, $this->secrets, $this->totp, $this->encrypter, $this->tokenHasher, $this->sessions, $this->events, $this->clock);
    }

    public function revokeAll(): RevokeAllRefreshTokensHandler
    {
        return new RevokeAllRefreshTokensHandler($this->refreshTokens, $this->clock);
    }

    public function ban(): BanAccountHandler
    {
        return new BanAccountHandler($this->accounts, $this->refreshTokens, $this->clock);
    }

    public function createAdmin(): CreateAdminHandler
    {
        return new CreateAdminHandler($this->accounts, $this->hasher, $this->policy, $this->enroller, $this->clock, $this->ids);
    }

    public function purge(): PurgeExpiredTokensHandler
    {
        return new PurgeExpiredTokensHandler($this->refreshTokens, $this->oneTimeTokens, $this->settings, $this->clock);
    }

    public function mapper(): AuthenticationEventMapper
    {
        return new AuthenticationEventMapper($this->ids);
    }

    /**
     * A verified account that can log in with {@see self::PASSWORD}.
     */
    public function verifiedAccount(string $email = 'ana@example.com', AccountRole $role = AccountRole::Driver): UserAccount
    {
        $now = $this->clock->now();
        $id = new AccountId($this->ids->generate());
        $emailObject = new Email($email);
        $hash = $this->hasher->hash(new PlainPassword(self::PASSWORD));
        $account = AccountRole::Admin === $role
            ? UserAccount::createAdmin($id, $emailObject, $hash, new Locale('en'), $now)
            : UserAccount::registerWithPassword($id, $emailObject, $hash, $role, null, new Locale('en'), $now);
        $account->verifyEmail($now);
        $this->accounts->save($account);
        $this->collector->releaseEvents();

        return $account;
    }

    /**
     * A currently valid TOTP code for the secret.
     */
    public function totpCode(string $secret, int $secondsFromNow = 0): string
    {
        assert('' !== $secret);

        return TotpCodes::at($secret, $this->clock->now()->getTimestamp() + $secondsFromNow);
    }

    /**
     * Integration events of the aggregates saved so far, released and mapped like the outbox does.
     *
     * @return list<IntegrationEvent>
     */
    public function aggregateEvents(): array
    {
        $events = [];
        foreach ($this->collector->releaseEvents() as $domainEvent) {
            if ($this->mapper()->supports($domainEvent)) {
                array_push($events, ...$this->mapper()->map($domainEvent));
            }
        }

        return $events;
    }
}
