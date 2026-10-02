<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Application;

use App\Authentication\Application\Command\ConfirmTwoFactorEnrolment;
use App\Authentication\Application\Command\ConfirmTwoFactorEnrolmentHandler;
use App\Authentication\Application\Command\CreateAdmin;
use App\Authentication\Application\Command\CreateAdminHandler;
use App\Authentication\Application\Command\Login;
use App\Authentication\Application\Command\PurgeExpiredTokens;
use App\Authentication\Application\Command\PurgeExpiredTokensHandler;
use App\Authentication\Application\Command\RefreshTokens;
use App\Authentication\Application\Command\RequestContext;
use App\Authentication\Application\Command\RequestPasswordReset;
use App\Authentication\Application\Command\RequestPasswordResetHandler;
use App\Authentication\Application\Command\ResetPassword;
use App\Authentication\Application\Command\ResetPasswordHandler;
use App\Authentication\Application\Command\Result\CreatedAdmin;
use App\Authentication\Application\Command\SocialLogin;
use App\Authentication\Application\Command\SocialLoginHandler;
use App\Authentication\Application\Command\StartTwoFactorEnrolment;
use App\Authentication\Application\Command\StartTwoFactorEnrolmentHandler;
use App\Authentication\Application\Command\VerifyTwoFactor;
use App\Authentication\Application\Command\VerifyTwoFactorHandler;
use App\Authentication\Application\Service\AuthenticationEventMapper;
use App\Authentication\Application\Service\TwoFactorEnroller;
use App\Authentication\Contract\Event\EmailVerifiedV1;
use App\Authentication\Contract\Event\LoginFailedV1;
use App\Authentication\Contract\Event\PasswordChangedV1;
use App\Authentication\Contract\Event\PasswordResetRequestedV1;
use App\Authentication\Contract\Event\TwoFactorEnabledV1;
use App\Authentication\Contract\Event\TwoFactorFailedV1;
use App\Authentication\Contract\Event\TwoFactorVerifiedV1;
use App\Authentication\Contract\Event\UserLoggedInV1;
use App\Authentication\Contract\Event\UserRegisteredV1;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use App\Authentication\Domain\Model\TokenHash;
use App\SharedKernel\Domain\DomainEvent;
use App\Tests\Support\Authentication\AuthenticationWorld;

use function count;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestPasswordResetHandler::class)]
#[CoversClass(ResetPasswordHandler::class)]
#[CoversClass(SocialLoginHandler::class)]
#[CoversClass(StartTwoFactorEnrolmentHandler::class)]
#[CoversClass(ConfirmTwoFactorEnrolmentHandler::class)]
#[CoversClass(VerifyTwoFactorHandler::class)]
#[CoversClass(CreateAdminHandler::class)]
#[CoversClass(PurgeExpiredTokensHandler::class)]
#[CoversClass(TwoFactorEnroller::class)]
#[CoversClass(AuthenticationEventMapper::class)]
final class OtherHandlersTest extends TestCase
{
    private AuthenticationWorld $world;
    private RequestContext $context;

    protected function setUp(): void
    {
        $this->world = new AuthenticationWorld();
        $this->context = new RequestContext('203.0.113.7', 'PHPUnit');
    }

    // ---- password reset ----

    public function testRequestingAResetSendsALinkAndPublishesAnEvent(): void
    {
        $account = $this->world->verifiedAccount();

        ($this->world->requestReset())(new RequestPasswordReset('ANA@example.com', $this->context));

        self::assertSame(1, $this->world->mailer->count('reset'));
        $stored = $this->world->oneTimeTokens->findByHash(OneTimeTokenPurpose::PasswordReset, $this->world->tokenHasher->hash($this->world->mailer->lastToken('reset')));
        self::assertEquals($this->world->clock->now()->modify('+1 hour'), $stored?->expiresAt());
        $events = $this->world->eventBus->of(PasswordResetRequestedV1::class);
        self::assertCount(1, $events);
        self::assertSame($account->id()->toString(), $events[0]->accountId->toString());
        self::assertSame('203.0.113.7', $events[0]->ip);
    }

    public function testRequestingAResetNeverRevealsWhetherTheAccountExists(): void
    {
        $banned = $this->world->verifiedAccount('b@example.com');
        $banned->ban();

        foreach (['nobody@example.com', 'not an email', '', 'b@example.com'] as $email) {
            ($this->world->requestReset())(new RequestPasswordReset($email, $this->context));
        }

        self::assertSame([], $this->world->mailer->sent);
        self::assertSame([], $this->world->eventBus->of(PasswordResetRequestedV1::class));
    }

    public function testAMailFailureWhileRequestingAResetIsSwallowed(): void
    {
        $this->world->verifiedAccount();
        $this->world->mailer->failing = true;

        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));

        $this->addToAssertionCount(1);
    }

    public function testASecondRequestKillsTheFirstLink(): void
    {
        $this->world->verifiedAccount();
        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));
        $first = $this->world->mailer->lastToken('reset');
        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));

        $this->expectException(AuthenticationProblem::class);

        ($this->world->resetPassword())(new ResetPassword($first, 'a brand new password'));
    }

    public function testResettingTheChangesThePasswordEndsSessionsAndVerifiesTheEmail(): void
    {
        $account = $this->world->verifiedAccount();
        $tokens = ($this->world->login())(new Login('ana@example.com', AuthenticationWorld::PASSWORD, $this->context))->tokens ?? throw new LogicException();
        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));

        ($this->world->resetPassword())(new ResetPassword($this->world->mailer->lastToken('reset'), 'a brand new password'));

        self::assertTrue(($this->world->login())(new Login('ana@example.com', 'a brand new password', $this->context))->isSuccess());
        self::assertFalse(($this->world->login())(new Login('ana@example.com', AuthenticationWorld::PASSWORD, $this->context))->isSuccess());
        self::assertSame('token-revoked', ($this->world->refresh())(new RefreshTokens($tokens->refreshToken, $this->context))->failure?->problemSlug());
        self::assertEquals($this->world->clock->now(), $account->passwordChangedAt());
        $this->world->aggregateEvents();
    }

    public function testResettingPublishesPasswordChanged(): void
    {
        $this->world->verifiedAccount();
        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));
        $this->world->aggregateEvents();

        ($this->world->resetPassword())(new ResetPassword($this->world->mailer->lastToken('reset'), 'a brand new password'));

        $events = $this->world->aggregateEvents();
        self::assertInstanceOf(PasswordChangedV1::class, $events[0]);
    }

    public function testAResetLinkWorksOnce(): void
    {
        $this->world->verifiedAccount();
        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));
        $token = $this->world->mailer->lastToken('reset');
        ($this->world->resetPassword())(new ResetPassword($token, 'a brand new password'));

        $this->expectException(AuthenticationProblem::class);

        ($this->world->resetPassword())(new ResetPassword($token, 'another new password'));
    }

    public function testAWeakNewPasswordIsRejectedAndTheLinkStaysValid(): void
    {
        $this->world->verifiedAccount();
        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));
        $token = $this->world->mailer->lastToken('reset');

        try {
            ($this->world->resetPassword())(new ResetPassword($token, 'short'));
            self::fail('A weak password was accepted.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('weak-password', $problem->problemSlug());
        }

        ($this->world->resetPassword())(new ResetPassword($token, 'a brand new password'));
        self::assertTrue(($this->world->login())(new Login('ana@example.com', 'a brand new password', $this->context))->isSuccess());
    }

    public function testAResetLinkExpiresAfterAnHour(): void
    {
        $this->world->verifiedAccount();
        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));
        $token = $this->world->mailer->lastToken('reset');
        $this->world->clock->advance('+1 hour -1 second');
        ($this->world->resetPassword())(new ResetPassword($token, 'a brand new password'));

        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));
        $second = $this->world->mailer->lastToken('reset');
        $this->world->clock->advance('+1 hour');

        $this->expectException(AuthenticationProblem::class);
        ($this->world->resetPassword())(new ResetPassword($second, 'yet another password'));
    }

    public function testAnUnknownResetTokenIsRejected(): void
    {
        $this->expectException(AuthenticationProblem::class);

        ($this->world->resetPassword())(new ResetPassword('0123456789012345678901234567890', 'a brand new password'));
    }

    public function testAVerificationTokenCannotBeUsedToResetAPassword(): void
    {
        ($this->world->register())(new \App\Authentication\Application\Command\RegisterUser('new@example.com', 'correct horse', 'DRIVER', null, 'en'));

        $this->expectException(AuthenticationProblem::class);

        ($this->world->resetPassword())(new ResetPassword($this->world->mailer->lastToken('verification'), 'a brand new password'));
    }

    public function testAResetGivesASocialOnlyAccountItsFirstPassword(): void
    {
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'soc@example.com');
        ($this->world->socialLogin())(new SocialLogin('google', 'tok', 'DRIVER', null, 'en', $this->context));
        ($this->world->requestReset())(new RequestPasswordReset('soc@example.com', $this->context));

        ($this->world->resetPassword())(new ResetPassword($this->world->mailer->lastToken('reset'), 'a brand new password'));

        self::assertTrue(($this->world->login())(new Login('soc@example.com', 'a brand new password', $this->context))->isSuccess());
    }

    // ---- social login ----

    public function testAFirstSocialSignInRegistersAVerifiedAccountAndLogsIn(): void
    {
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'soc@example.com');

        $result = ($this->world->socialLogin())(new SocialLogin('google', 'tok', 'TOWER', '+381641234567', 'sr_Latn', $this->context));

        self::assertTrue($result->isSuccess());
        self::assertStringEndsWith('|ROLE_TOWER|social', $result->tokens->accessToken ?? '');
        $account = $this->world->accounts->findByEmail(new Email('soc@example.com'));
        self::assertNotNull($account);
        self::assertTrue($account->isEmailVerified());
        self::assertSame('+381641234567', $account->phone()?->toString());
        self::assertSame([], $this->world->mailer->sent);
        $registered = $this->world->aggregateEvents()[0];
        self::assertInstanceOf(UserRegisteredV1::class, $registered);
        self::assertTrue($registered->emailVerified);
        self::assertSame('social:google', $this->world->eventBus->of(UserLoggedInV1::class)[0]->method);
    }

    public function testASecondSignInFindsTheSameAccount(): void
    {
        $this->world->social->trust('tok', SocialProvider::Apple, 'a-1', 'soc@example.com');
        ($this->world->socialLogin())(new SocialLogin('apple', 'tok', 'DRIVER', null, 'en', $this->context));

        $result = ($this->world->socialLogin())(new SocialLogin('apple', 'tok', null, null, 'en', $this->context));

        self::assertTrue($result->isSuccess());
        self::assertCount(1, $this->world->aggregateEvents());
    }

    public function testASocialSignInLinksTheIdentityToAnExistingAccountWithTheSameVerifiedEmail(): void
    {
        $account = $this->world->verifiedAccount('ana@example.com');
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'ana@example.com');

        $result = ($this->world->socialLogin())(new SocialLogin('google', 'tok', null, null, 'en', $this->context));

        self::assertTrue($result->isSuccess());
        self::assertTrue($account->hasSocialIdentity(new SocialIdentity(SocialProvider::Google, new SocialSubject('g-1'))));
    }

    public function testLinkingAnUnconfirmedAccountDiscardsThePasswordSessionsAndTokensOfWhoeverRegisteredIt(): void
    {
        ($this->world->register())(new \App\Authentication\Application\Command\RegisterUser('victim@example.com', 'attacker password', 'DRIVER', null, 'en'));
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'victim@example.com');

        $result = ($this->world->socialLogin())(new SocialLogin('google', 'tok', null, null, 'en', $this->context));

        self::assertTrue($result->isSuccess());
        $account = $this->world->accounts->findByEmail(new Email('victim@example.com'));
        self::assertNotNull($account);
        self::assertTrue($account->isEmailVerified());
        self::assertFalse($account->hasPassword(), 'the attacker password is gone');
        $this->expectException(AuthenticationProblem::class);
        ($this->world->resetPassword())(new ResetPassword($this->world->mailer->lastToken('verification'), 'a brand new password'));
    }

    public function testLinkingAConfirmedAccountKeepsItsPassword(): void
    {
        $account = $this->world->verifiedAccount('ana@example.com');
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'ana@example.com');

        ($this->world->socialLogin())(new SocialLogin('google', 'tok', null, null, 'en', $this->context));

        self::assertTrue($account->hasPassword());
        self::assertTrue(($this->world->login())(new Login('ana@example.com', AuthenticationWorld::PASSWORD, $this->context))->isSuccess());
    }

    public function testAnEmailTheProviderDoesNotConfirmIsNeverLinkedToAnExistingAccount(): void
    {
        $account = $this->world->verifiedAccount('ana@example.com');
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'ana@example.com', false);

        try {
            ($this->world->socialLogin())(new SocialLogin('google', 'tok', null, null, 'en', $this->context));
            self::fail('An unverified provider email took over an account.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('email-already-registered', $problem->problemSlug());
        }
        self::assertSame([], $account->socialIdentities());
    }

    public function testAnUnconfirmedProviderEmailRegistersAnAccountThatMustVerifyFirst(): void
    {
        $this->world->social->trust('tok', SocialProvider::Apple, 'a-1', 'new@example.com', false);

        $result = ($this->world->socialLogin())(new SocialLogin('apple', 'tok', 'DRIVER', null, 'en', $this->context));

        self::assertSame('email-not-verified', $result->failure?->problemSlug());
        self::assertSame(1, $this->world->mailer->count('verification'));
        self::assertNotNull($this->world->accounts->findByEmail(new Email('new@example.com')));
        self::assertSame([], $this->world->refreshTokens->all());
    }

    public function testASocialSignInNeedsARoleTheFirstTime(): void
    {
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'soc@example.com');

        try {
            ($this->world->socialLogin())(new SocialLogin('google', 'tok', null, null, 'en', $this->context));
            self::fail('An account was created without a role.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('social-role-required', $problem->problemSlug());
        }
        self::assertNull($this->world->accounts->findByEmail(new Email('soc@example.com')));
    }

    public function testAnInvalidIdTokenIsRejected(): void
    {
        $this->expectException(AuthenticationProblem::class);
        $this->expectExceptionMessage('could not be verified');

        ($this->world->socialLogin())(new SocialLogin('google', 'forged', 'DRIVER', null, 'en', $this->context));
    }

    public function testAnUnsupportedProviderIsRejected(): void
    {
        $this->expectException(\App\Authentication\Domain\Exception\InvalidValue::class);

        ($this->world->socialLogin())(new SocialLogin('facebook', 'tok', 'DRIVER', null, 'en', $this->context));
    }

    public function testBlacklistedContactsCannotRegisterSocially(): void
    {
        $this->world->blacklist->blocked = ['soc@example.com'];
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'soc@example.com');

        $this->expectException(AuthenticationProblem::class);
        $this->expectExceptionMessage('cannot be used to register');

        ($this->world->socialLogin())(new SocialLogin('google', 'tok', 'DRIVER', null, 'en', $this->context));
    }

    public function testAdminsCannotSignInSociallyNorBeLinkedByEmail(): void
    {
        $this->world->verifiedAccount('root@example.com', AccountRole::Admin);
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'root@example.com');

        try {
            ($this->world->socialLogin())(new SocialLogin('google', 'tok', null, null, 'en', $this->context));
            self::fail('An admin signed in socially.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('admin-social-login-forbidden', $problem->problemSlug());
        }
    }

    public function testABannedSocialAccountCannotSignIn(): void
    {
        $this->world->social->trust('tok', SocialProvider::Google, 'g-1', 'soc@example.com');
        ($this->world->socialLogin())(new SocialLogin('google', 'tok', 'DRIVER', null, 'en', $this->context));
        $this->world->accounts->findByEmail(new Email('soc@example.com'))?->ban();

        $result = ($this->world->socialLogin())(new SocialLogin('google', 'tok', null, null, 'en', $this->context));

        self::assertSame('account-banned', $result->failure?->problemSlug());
        self::assertSame('account-banned', $this->world->eventBus->of(LoginFailedV1::class)[0]->reason);
    }

    // ---- two-factor ----

    /**
     * @return array{CreatedAdmin, list<string>} the new admin and the recovery codes of the confirmed enrolment
     */
    private function adminWithEnrolment(): array
    {
        $created = ($this->world->createAdmin())(new CreateAdmin('root@example.com', 'a long admin passphrase'));
        $codes = ($this->world->confirmEnrolment())(new ConfirmTwoFactorEnrolment($created->accountId, $this->world->totpCode($created->enrolment->secret)))->codes;
        $this->world->clock->advance('+30 seconds');

        return [$created, $codes];
    }

    public function testCreatingAnAdminStartsTheEnrolment(): void
    {
        $created = ($this->world->createAdmin())(new CreateAdmin('Root@Example.com', 'a long admin passphrase'));

        $account = $this->world->accounts->findByEmail(new Email('root@example.com'));
        self::assertSame(AccountRole::Admin, $account?->role());
        self::assertTrue($account->isEmailVerified());
        self::assertSame($account->id()->toString(), $created->accountId);
        self::assertStringStartsWith('otpauth://totp/', $created->enrolment->provisioningUri);
        self::assertStringContainsString('Slep', $created->enrolment->provisioningUri);
        self::assertMatchesRegularExpression('/^[A-Z2-7]{16,}$/', $created->enrolment->secret);
        $stored = $this->world->secrets->findByAccount($account->id());
        self::assertFalse($stored?->isConfirmed());
        self::assertStringNotContainsString($created->enrolment->secret, $stored->encryptedSecret());
        self::assertSame($created->enrolment->secret, $this->world->encrypter->decrypt($stored->encryptedSecret()));
    }

    public function testCreatingAnAdminRefusesDuplicatesAndWeakPasswords(): void
    {
        ($this->world->createAdmin())(new CreateAdmin('root@example.com', 'a long admin passphrase'));

        foreach ([['root@example.com', 'a long admin passphrase', 'email-already-registered'], ['other@example.com', 'weak', 'weak-password']] as [$email, $password, $slug]) {
            try {
                ($this->world->createAdmin())(new CreateAdmin($email, $password));
                self::fail('The admin was created.');
            } catch (AuthenticationProblem $problem) {
                self::assertSame($slug, $problem->problemSlug());
            }
        }
    }

    public function testConfirmingTheEnrolmentReturnsTenRecoveryCodesOnce(): void
    {
        [$created, $codes] = $this->adminWithEnrolment();

        self::assertCount(10, $codes);
        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/', $code);
        }
        $stored = $this->world->secrets->findByAccount(new \App\Authentication\Domain\Model\AccountId($created->accountId));
        self::assertTrue($stored?->isConfirmed());
        self::assertCount(10, $stored->recoveryCodeHashes());
        self::assertTrue($stored->hasRecoveryCode($this->world->tokenHasher->hash($codes[0])));
        self::assertNotContains($codes[0], array_map(static fn (TokenHash $hash): string => $hash->value(), $stored->recoveryCodeHashes()));
        $events = $this->world->aggregateEvents();
        self::assertInstanceOf(TwoFactorEnabledV1::class, $events[count($events) - 1]);
    }

    public function testConfirmingWithAWrongCodeLeavesTheEnrolmentUnconfirmed(): void
    {
        $created = ($this->world->createAdmin())(new CreateAdmin('root@example.com', 'a long admin passphrase'));

        try {
            ($this->world->confirmEnrolment())(new ConfirmTwoFactorEnrolment($created->accountId, '000000'));
            self::fail('A wrong code confirmed the enrolment.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('two-factor-invalid', $problem->problemSlug());
        }
        self::assertFalse($this->world->secrets->findByAccount(new \App\Authentication\Domain\Model\AccountId($created->accountId))?->isConfirmed());
    }

    public function testConfirmingWithoutAnEnrolmentOrTwiceIsAConflict(): void
    {
        $account = $this->world->verifiedAccount('unenrolled@example.com', AccountRole::Admin);
        try {
            ($this->world->confirmEnrolment())(new ConfirmTwoFactorEnrolment($account->id()->toString(), '123456'));
            self::fail('Confirmed without an enrolment.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('two-factor-not-enrolled', $problem->problemSlug());
        }

        [$created] = $this->adminWithEnrolment();
        $this->expectException(AuthenticationProblem::class);
        ($this->world->confirmEnrolment())(new ConfirmTwoFactorEnrolment($created->accountId, '123456'));
    }

    public function testStartingAnEnrolmentAgainReplacesAnUnconfirmedSecret(): void
    {
        $created = ($this->world->createAdmin())(new CreateAdmin('root@example.com', 'a long admin passphrase'));

        $again = ($this->world->startEnrolment())(new StartTwoFactorEnrolment($created->accountId));

        self::assertNotSame($created->enrolment->secret, $again->secret);
        $stored = $this->world->secrets->findByAccount(new \App\Authentication\Domain\Model\AccountId($created->accountId));
        self::assertSame($again->secret, $this->world->encrypter->decrypt($stored?->encryptedSecret() ?? ''));
    }

    public function testAConfirmedEnrolmentCannotBeRestarted(): void
    {
        [$created] = $this->adminWithEnrolment();

        $this->expectException(AuthenticationProblem::class);
        $this->expectExceptionMessage('already set up');

        ($this->world->startEnrolment())(new StartTwoFactorEnrolment($created->accountId));
    }

    public function testOnlyAdminsCanEnrol(): void
    {
        $driver = $this->world->verifiedAccount();

        foreach ([$driver->id()->toString() => 'admin-only', '01900000-0000-7000-8000-00000000ffff' => 'token-invalid'] as $id => $slug) {
            try {
                ($this->world->startEnrolment())(new StartTwoFactorEnrolment($id));
                self::fail('A non-admin enrolled.');
            } catch (AuthenticationProblem $problem) {
                self::assertSame($slug, $problem->problemSlug());
            }
        }
    }

    public function testAValidCodeCompletesTheSecondFactor(): void
    {
        [$created] = $this->adminWithEnrolment();

        $result = ($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, $this->world->totpCode($created->enrolment->secret), $this->context));

        self::assertTrue($result->isSuccess());
        self::assertStringEndsWith('|ROLE_ADMIN|pwd+otp', $result->tokens->accessToken ?? '');
        $verified = $this->world->eventBus->of(TwoFactorVerifiedV1::class);
        self::assertCount(1, $verified);
        self::assertSame('totp', $verified[0]->method);
        self::assertSame('pwd+otp', $this->world->eventBus->of(UserLoggedInV1::class)[0]->method);
    }

    public function testACodeFromTheNeighbouringStepsIsAccepted(): void
    {
        [$created] = $this->adminWithEnrolment();

        $result = ($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, $this->world->totpCode($created->enrolment->secret, 30), $this->context));

        self::assertTrue($result->isSuccess());
    }

    public function testACodeTwoStepsAwayIsRejected(): void
    {
        [$created] = $this->adminWithEnrolment();

        $result = ($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, $this->world->totpCode($created->enrolment->secret, 60), $this->context));

        self::assertSame('two-factor-invalid', $result->failure?->problemSlug());
        self::assertCount(1, $this->world->eventBus->of(TwoFactorFailedV1::class));
        self::assertSame([], $this->world->refreshTokens->all());
    }

    public function testACodeCannotBeReplayed(): void
    {
        [$created] = $this->adminWithEnrolment();
        $code = $this->world->totpCode($created->enrolment->secret);
        self::assertTrue(($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, $code, $this->context))->isSuccess());

        $replay = ($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, $code, $this->context));

        self::assertSame('two-factor-invalid', $replay->failure?->problemSlug());
    }

    public function testTheCodeThatConfirmedTheEnrolmentCannotBeReusedToLogIn(): void
    {
        $created = ($this->world->createAdmin())(new CreateAdmin('root@example.com', 'a long admin passphrase'));
        $code = $this->world->totpCode($created->enrolment->secret);
        ($this->world->confirmEnrolment())(new ConfirmTwoFactorEnrolment($created->accountId, $code));

        $result = ($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, $code, $this->context));

        self::assertSame('two-factor-invalid', $result->failure?->problemSlug());
    }

    public function testARecoveryCodeWorksOnceAndIsReportedAsSuch(): void
    {
        [$created, $codes] = $this->adminWithEnrolment();

        $first = ($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, strtoupper($codes[0]), $this->context));
        $second = ($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, $codes[0], $this->context));

        self::assertTrue($first->isSuccess());
        self::assertSame('recovery_code', $this->world->eventBus->of(TwoFactorVerifiedV1::class)[0]->method);
        self::assertSame('two-factor-invalid', $second->failure?->problemSlug());
    }

    public function testWrongInputsAreRejected(): void
    {
        [$created] = $this->adminWithEnrolment();

        foreach (['', 'abcdef', '12345', '1234567', 'zzzz-zzzz-zzzz'] as $code) {
            $result = ($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, $code, $this->context));
            self::assertSame('two-factor-invalid', $result->failure?->problemSlug(), "code: $code");
        }
        self::assertCount(5, $this->world->eventBus->of(TwoFactorFailedV1::class));
    }

    public function testTheSecondFactorNeedsAConfirmedEnrolmentAnAdminAndNoBan(): void
    {
        $unenrolled = $this->world->verifiedAccount('unenrolled@example.com', AccountRole::Admin);
        $driver = $this->world->verifiedAccount('d@example.com');
        foreach ([$unenrolled->id()->toString() => 'two-factor-not-enrolled', $driver->id()->toString() => 'admin-only', '01900000-0000-7000-8000-00000000ffff' => 'token-invalid'] as $id => $slug) {
            try {
                ($this->world->verifyTwoFactor())(new VerifyTwoFactor($id, '123456', $this->context));
                self::fail('Verified without a valid setup.');
            } catch (AuthenticationProblem $problem) {
                self::assertSame($slug, $problem->problemSlug());
            }
        }

        [$created] = $this->adminWithEnrolment();
        $this->world->accounts->findByEmail(new Email('root@example.com'))?->ban();
        $banned = ($this->world->verifyTwoFactor())(new VerifyTwoFactor($created->accountId, $this->world->totpCode($created->enrolment->secret), $this->context));
        self::assertSame('account-banned', $banned->failure?->problemSlug());
    }

    // ---- purge ----

    public function testPurgingDeletesTokensThatExpiredMoreThanThirtyDaysAgo(): void
    {
        $this->world->verifiedAccount();
        ($this->world->login())(new Login('ana@example.com', AuthenticationWorld::PASSWORD, $this->context));
        ($this->world->requestReset())(new RequestPasswordReset('ana@example.com', $this->context));

        $this->world->clock->advance('+30 days +1 hour');
        self::assertSame(0, ($this->world->purge())(new PurgeExpiredTokens()), 'tokens expired less than 30 days ago are kept');

        $this->world->clock->advance('+30 days');
        self::assertSame(2, ($this->world->purge())(new PurgeExpiredTokens()));
        self::assertSame(0, ($this->world->purge())(new PurgeExpiredTokens()), 'running it twice changes nothing');
    }

    // ---- event mapper ----

    public function testTheMapperIgnoresForeignEvents(): void
    {
        $foreign = new class implements DomainEvent {
            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
        };

        self::assertFalse($this->world->mapper()->supports($foreign));
        self::assertSame([], $this->world->mapper()->map($foreign));
    }

    public function testTheMapperTurnsEveryAccountEventIntoItsIntegrationEvent(): void
    {
        $this->world->verifiedAccount();
        ($this->world->register())(new \App\Authentication\Application\Command\RegisterUser('new@example.com', 'correct horse', 'DRIVER', null, 'en'));
        ($this->world->verifyEmail())(new \App\Authentication\Application\Command\VerifyEmail($this->world->mailer->lastToken('verification')));

        $events = $this->world->aggregateEvents();

        self::assertContainsOnlyInstancesOf(\App\SharedKernel\Contract\IntegrationEvent::class, $events);
        self::assertSame([UserRegisteredV1::class, EmailVerifiedV1::class], array_map(static fn (object $e): string => $e::class, $events));
        $ids = array_map(static fn ($e): string => $e->eventId(), $events);
        self::assertCount(2, array_unique($ids), 'every event has its own id');
    }
}
