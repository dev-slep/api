<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Application;

use App\Authentication\Application\Command\RegisterUser;
use App\Authentication\Application\Command\RegisterUserHandler;
use App\Authentication\Application\Command\RequestContext;
use App\Authentication\Application\Command\ResendEmailVerification;
use App\Authentication\Application\Command\ResendEmailVerificationHandler;
use App\Authentication\Application\Command\VerifyEmail;
use App\Authentication\Application\Command\VerifyEmailHandler;
use App\Authentication\Contract\Event\EmailVerifiedV1;
use App\Authentication\Contract\Event\UserRegisteredV1;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Exception\InvalidValue;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Tests\Support\Authentication\AuthenticationWorld;

use function count;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterUserHandler::class)]
#[CoversClass(VerifyEmailHandler::class)]
#[CoversClass(ResendEmailVerificationHandler::class)]
final class RegistrationHandlersTest extends TestCase
{
    private AuthenticationWorld $world;

    protected function setUp(): void
    {
        $this->world = new AuthenticationWorld();
    }

    private function register(string $email = 'ana@example.com', string $password = 'correct horse', string $role = 'DRIVER', ?string $phone = '+381641234567', string $locale = 'sr_Latn'): void
    {
        ($this->world->register())(new RegisterUser($email, $password, $role, $phone, $locale));
    }

    public function testRegisteringCreatesAnUnverifiedAccountAndSendsAVerificationEmail(): void
    {
        $this->register('  Ana@Example.com ');

        $account = $this->world->accounts->findByEmail(new Email('ana@example.com'));
        self::assertNotNull($account);
        self::assertFalse($account->isEmailVerified());
        self::assertTrue($this->world->hasher->verify(new \App\Authentication\Domain\Model\PlainPassword('correct horse'), $account->passwordHash() ?? throw new LogicException()));
        self::assertSame(1, $this->world->mailer->count('verification'));
        self::assertSame('ana@example.com', $this->world->mailer->sent[0]['to']);
        self::assertSame('sr_Latn', $this->world->mailer->sent[0]['locale']);
    }

    public function testOnlyTheHashOfTheVerificationTokenIsStored(): void
    {
        $this->register();

        $raw = $this->world->mailer->lastToken('verification');
        $stored = $this->world->oneTimeTokens->findByHash(OneTimeTokenPurpose::EmailVerification, $this->world->tokenHasher->hash($raw));

        self::assertNotNull($stored);
        self::assertNull($this->world->oneTimeTokens->findByHash(OneTimeTokenPurpose::EmailVerification, $this->world->tokenHasher->hash('not-the-token-at-all-0000')));
        self::assertEquals($this->world->clock->now()->modify('+24 hours'), $stored->expiresAt());
    }

    public function testRegisteringPublishesUserRegisteredWithoutPasswordData(): void
    {
        $this->register(role: 'tower');

        $events = $this->world->aggregateEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(UserRegisteredV1::class, $event);
        self::assertSame('ana@example.com', $event->email);
        self::assertSame('TOWER', $event->role);
        self::assertSame('+381641234567', $event->phone);
        self::assertSame('sr_Latn', $event->locale);
        self::assertFalse($event->emailVerified);
        self::assertStringNotContainsString('horse', serialize($event));
    }

    public function testThePhoneIsOptional(): void
    {
        $this->register(phone: null);

        self::assertNull($this->world->accounts->findByEmail(new Email('ana@example.com'))?->phone());
    }

    public function testARegisteredEmailCannotRegisterAgain(): void
    {
        $this->register();

        try {
            $this->register('ANA@example.com');
            self::fail('A duplicate email registered.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('email-already-registered', $problem->problemSlug());
        }
        self::assertSame(1, $this->world->mailer->count('verification'));
    }

    public function testBlacklistedEmailsAndPhonesCannotRegister(): void
    {
        $this->world->blacklist->blocked = ['bad@example.com', '+381600000000'];

        foreach ([['bad@example.com', '+381641234567'], ['ok@example.com', '+381600000000']] as [$email, $phone]) {
            try {
                $this->register($email, phone: $phone);
                self::fail('A blacklisted contact registered.');
            } catch (AuthenticationProblem $problem) {
                self::assertSame('contact-blacklisted', $problem->problemSlug());
            }
        }
        self::assertNull($this->world->accounts->findByEmail(new Email('ok@example.com')));
    }

    public function testWeakPasswordsAreRejected(): void
    {
        $this->expectException(AuthenticationProblem::class);
        $this->expectExceptionMessage('at least 10');

        $this->register(password: 'short');
    }

    public function testAdminsCannotBeRegistered(): void
    {
        $this->expectException(AuthenticationProblem::class);
        $this->expectExceptionMessage('Admin accounts cannot');

        $this->register(role: 'ADMIN');
    }

    /**
     * @return iterable<string, array{array{email?: string, password?: string, role?: string, phone?: string, locale?: string}}>
     */
    public static function invalidInput(): iterable
    {
        yield 'bad email' => [['email' => 'nope']];
        yield 'unknown role' => [['role' => 'PILOT']];
        yield 'bad phone' => [['phone' => 'abc']];
        yield 'bad locale' => [['locale' => 'EN']];
        yield 'empty password' => [['password' => '']];
    }

    /**
     * @param array{email?: string, password?: string, role?: string, phone?: string, locale?: string} $override
     */
    #[DataProvider('invalidInput')]
    public function testInvalidInputIsRejectedBeforeAnythingIsSaved(array $override): void
    {
        $input = $override + ['email' => 'ana@example.com', 'password' => 'correct horse', 'role' => 'DRIVER', 'phone' => '+381641234567', 'locale' => 'en'];

        try {
            $this->register(...$input);
            self::fail('Invalid input was accepted.');
        } catch (InvalidValue) {
            self::assertNull($this->world->accounts->findByEmail(new Email('ana@example.com')));
            self::assertSame([], $this->world->mailer->sent);
        }
    }

    public function testAFailingMailServerStopsTheRegistration(): void
    {
        $this->world->mailer->failing = true;

        $this->expectException(\App\Authentication\Application\Port\EmailDeliveryFailed::class);

        $this->register();
    }

    public function testFollowingTheEmailLinkVerifiesTheAccount(): void
    {
        $this->register();

        ($this->world->verifyEmail())(new VerifyEmail($this->world->mailer->lastToken('verification')));

        self::assertTrue($this->world->accounts->findByEmail(new Email('ana@example.com'))?->isEmailVerified());
        $events = $this->world->aggregateEvents();
        self::assertInstanceOf(EmailVerifiedV1::class, $events[count($events) - 1]);
    }

    public function testAVerificationLinkWorksOnlyOnce(): void
    {
        $this->register();
        $token = $this->world->mailer->lastToken('verification');
        ($this->world->verifyEmail())(new VerifyEmail($token));

        $this->expectException(AuthenticationProblem::class);

        ($this->world->verifyEmail())(new VerifyEmail($token));
    }

    public function testAnUnknownVerificationTokenIsRejected(): void
    {
        $this->expectException(AuthenticationProblem::class);

        ($this->world->verifyEmail())(new VerifyEmail('0123456789012345678901234567890'));
    }

    public function testAVerificationLinkExpiresAfter24Hours(): void
    {
        $this->register();
        $token = $this->world->mailer->lastToken('verification');

        $this->world->clock->advance('+24 hours -1 second');
        ($this->world->verifyEmail())(new VerifyEmail($token));
        self::assertTrue($this->world->accounts->findByEmail(new Email('ana@example.com'))?->isEmailVerified());
    }

    public function testAVerificationLinkIsDeadExactlyAtItsExpiry(): void
    {
        $this->register();
        $token = $this->world->mailer->lastToken('verification');
        $this->world->clock->advance('+24 hours');

        try {
            ($this->world->verifyEmail())(new VerifyEmail($token));
            self::fail('An expired link worked.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('verification-token-invalid', $problem->problemSlug());
        }
        self::assertFalse($this->world->accounts->findByEmail(new Email('ana@example.com'))?->isEmailVerified());
    }

    public function testResendingSendsANewLinkAndKillsTheOldOne(): void
    {
        $this->register();
        $old = $this->world->mailer->lastToken('verification');

        ($this->world->resendVerification())(new ResendEmailVerification('ana@example.com'));

        self::assertSame(2, $this->world->mailer->count('verification'));
        $new = $this->world->mailer->lastToken('verification');
        self::assertNotSame($old, $new);
        try {
            ($this->world->verifyEmail())(new VerifyEmail($old));
            self::fail('The replaced link still worked.');
        } catch (AuthenticationProblem) {
        }
        ($this->world->verifyEmail())(new VerifyEmail($new));
        self::assertTrue($this->world->accounts->findByEmail(new Email('ana@example.com'))?->isEmailVerified());
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function silentResends(): iterable
    {
        yield 'unknown email' => ['nobody@example.com'];
        yield 'malformed email' => ['not an email'];
        yield 'empty' => [''];
    }

    #[DataProvider('silentResends')]
    public function testResendingNeverRevealsWhetherTheAccountExists(string $email): void
    {
        ($this->world->resendVerification())(new ResendEmailVerification($email));

        self::assertSame([], $this->world->mailer->sent);
    }

    public function testResendingToAVerifiedOrBannedAccountSendsNothing(): void
    {
        $verified = $this->world->verifiedAccount('v@example.com');
        $banned = $this->world->verifiedAccount('b@example.com');
        $banned->ban();

        ($this->world->resendVerification())(new ResendEmailVerification($verified->email()->toString()));
        ($this->world->resendVerification())(new ResendEmailVerification('b@example.com'));

        self::assertSame([], $this->world->mailer->sent);
    }

    public function testAMailFailureWhileResendingIsNotReportedToTheCaller(): void
    {
        $this->register();
        $this->world->mailer->failing = true;

        ($this->world->resendVerification())(new ResendEmailVerification('ana@example.com'));

        $this->addToAssertionCount(1);
    }

    public function testRequestContextDefaultsToNothing(): void
    {
        $context = new RequestContext();

        self::assertNull($context->ip);
        self::assertNull($context->userAgent);
    }
}
