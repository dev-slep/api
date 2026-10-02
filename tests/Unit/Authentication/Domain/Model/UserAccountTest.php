<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Domain\Model;

use App\Authentication\Domain\Event\EmailVerified;
use App\Authentication\Domain\Event\PasswordChanged;
use App\Authentication\Domain\Event\UserRegistered;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\AccountStatus;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\PasswordHash;
use App\Authentication\Domain\Model\PhoneNumber;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use App\Authentication\Domain\Model\UserAccount;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserAccount::class)]
#[CoversClass(UserRegistered::class)]
#[CoversClass(EmailVerified::class)]
#[CoversClass(PasswordChanged::class)]
final class UserAccountTest extends TestCase
{
    private const string ID = '01900000-0000-7000-8000-000000000001';

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
    }

    private function passwordAccount(AccountRole $role = AccountRole::Driver): UserAccount
    {
        return UserAccount::registerWithPassword(new AccountId(self::ID), new Email('ana@example.com'), new PasswordHash('hash'), $role, new PhoneNumber('+381641234567'), new Locale('sr_Latn'), $this->now);
    }

    private function google(string $subject = 'g-1'): SocialIdentity
    {
        return new SocialIdentity(SocialProvider::Google, new SocialSubject($subject));
    }

    public function testRegisteringWithAPasswordCreatesAnUnverifiedActiveAccount(): void
    {
        $account = $this->passwordAccount(AccountRole::Tower);

        self::assertSame(self::ID, $account->id()->toString());
        self::assertSame('ana@example.com', $account->email()->toString());
        self::assertSame('hash', $account->passwordHash()?->value());
        self::assertTrue($account->hasPassword());
        self::assertSame(AccountRole::Tower, $account->role());
        self::assertSame('+381641234567', $account->phone()?->toString());
        self::assertSame('sr_Latn', $account->locale()->toString());
        self::assertFalse($account->isEmailVerified());
        self::assertNull($account->emailVerifiedAt());
        self::assertSame(AccountStatus::Active, $account->status());
        self::assertFalse($account->isBanned());
        self::assertSame([], $account->socialIdentities());
        self::assertEquals($this->now, $account->registeredAt());
        self::assertNull($account->passwordChangedAt());
    }

    public function testRegistrationRecordsAnEvent(): void
    {
        $events = $this->passwordAccount()->releaseEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(UserRegistered::class, $event);
        self::assertSame(AccountRole::Driver, $event->role);
        self::assertFalse($event->emailVerified);
        self::assertEquals($this->now, $event->occurredAt());
    }

    public function testAdminsCannotRegisterThemselves(): void
    {
        $this->expectException(AuthenticationProblem::class);
        $this->expectExceptionMessage('Admin accounts cannot be registered');

        $this->passwordAccount(AccountRole::Admin);
    }

    public function testAdminsCannotRegisterWithASocialIdentityEither(): void
    {
        $this->expectException(AuthenticationProblem::class);

        UserAccount::registerWithSocialIdentity(new AccountId(self::ID), new Email('a@example.com'), AccountRole::Admin, null, new Locale('en'), $this->google(), true, $this->now);
    }

    public function testASocialAccountStartsVerifiedWhenTheProviderConfirmsTheEmail(): void
    {
        $account = UserAccount::registerWithSocialIdentity(new AccountId(self::ID), new Email('a@example.com'), AccountRole::Driver, null, new Locale('en'), $this->google(), true, $this->now);

        self::assertTrue($account->isEmailVerified());
        self::assertFalse($account->hasPassword());
        self::assertNull($account->phone());
        self::assertTrue($account->hasSocialIdentity($this->google()));
        $event = $account->releaseEvents()[0];
        self::assertInstanceOf(UserRegistered::class, $event);
        self::assertTrue($event->emailVerified);
    }

    public function testASocialAccountIsUnverifiedWhenTheProviderDoesNotConfirmTheEmail(): void
    {
        $account = UserAccount::registerWithSocialIdentity(new AccountId(self::ID), new Email('a@example.com'), AccountRole::Tower, null, new Locale('en'), $this->google(), false, $this->now);

        self::assertFalse($account->isEmailVerified());
    }

    public function testAnAdminIsCreatedVerifiedWithTheAdminRole(): void
    {
        $account = UserAccount::createAdmin(new AccountId(self::ID), new Email('root@example.com'), new PasswordHash('hash'), new Locale('en'), $this->now);

        self::assertSame(AccountRole::Admin, $account->role());
        self::assertTrue($account->isEmailVerified());
        $event = $account->releaseEvents()[0];
        self::assertInstanceOf(UserRegistered::class, $event);
        self::assertSame(AccountRole::Admin, $event->role);
        self::assertTrue($event->emailVerified);
    }

    public function testVerifyingTheEmailRecordsAnEventOnce(): void
    {
        $account = $this->passwordAccount();
        $account->releaseEvents();
        $later = $this->now->modify('+1 hour');

        $account->verifyEmail($later);
        $account->verifyEmail($later->modify('+1 hour'));

        self::assertTrue($account->isEmailVerified());
        self::assertEquals($later, $account->emailVerifiedAt());
        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(EmailVerified::class, $events[0]);
        self::assertEquals($later, $events[0]->occurredAt());
    }

    public function testChangingThePasswordStoresTheHashAndRecordsAnEvent(): void
    {
        $account = $this->passwordAccount();
        $account->releaseEvents();

        $account->changePassword(new PasswordHash('new-hash'), $this->now->modify('+1 day'));

        self::assertSame('new-hash', $account->passwordHash()?->value());
        self::assertEquals($this->now->modify('+1 day'), $account->passwordChangedAt());
        self::assertInstanceOf(PasswordChanged::class, $account->releaseEvents()[0]);
    }

    public function testUpgradingTheHashRecordsNoEvent(): void
    {
        $account = $this->passwordAccount();
        $account->releaseEvents();

        $account->upgradePasswordHash(new PasswordHash('stronger'));

        self::assertSame('stronger', $account->passwordHash()?->value());
        self::assertNull($account->passwordChangedAt());
        self::assertSame([], $account->releaseEvents());
    }

    public function testASocialOnlyAccountGetsItsFirstPasswordFromAReset(): void
    {
        $account = UserAccount::registerWithSocialIdentity(new AccountId(self::ID), new Email('a@example.com'), AccountRole::Driver, null, new Locale('en'), $this->google(), true, $this->now);

        $account->changePassword(new PasswordHash('first'), $this->now);

        self::assertTrue($account->hasPassword());
    }

    public function testTheUnconfirmedPasswordOfAnAccountCanBeDiscarded(): void
    {
        $account = $this->passwordAccount();

        $account->discardUnconfirmedPassword();

        self::assertFalse($account->hasPassword());
    }

    public function testAConfirmedAccountKeepsItsPassword(): void
    {
        $account = $this->passwordAccount();
        $account->verifyEmail($this->now);

        $account->discardUnconfirmedPassword();

        self::assertTrue($account->hasPassword());
    }

    public function testLinkingAnIdentityWithAProviderConfirmedEmailVerifiesTheAccount(): void
    {
        $account = $this->passwordAccount();

        $account->linkSocialIdentity($this->google(), true, $this->now);

        self::assertTrue($account->hasSocialIdentity($this->google()));
        self::assertCount(1, $account->socialIdentities());
        self::assertTrue($account->isEmailVerified());
    }

    public function testLinkingAnIdentityWithoutProviderConfirmationLeavesTheEmailUnverified(): void
    {
        $account = $this->passwordAccount();

        $account->linkSocialIdentity($this->google(), false, $this->now);

        self::assertFalse($account->isEmailVerified());
    }

    public function testLinkingTheSameIdentityTwiceIsRejectedAndChangesNothing(): void
    {
        $account = $this->passwordAccount();
        $account->linkSocialIdentity($this->google(), true, $this->now);

        try {
            $account->linkSocialIdentity($this->google(), true, $this->now);
            self::fail('The duplicate identity was accepted.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('identity-already-linked', $problem->problemSlug());
        }
        self::assertCount(1, $account->socialIdentities());
    }

    public function testTheSameSubjectOfAnotherProviderIsADifferentIdentity(): void
    {
        $account = $this->passwordAccount();
        $account->linkSocialIdentity($this->google('same'), true, $this->now);

        $account->linkSocialIdentity(new SocialIdentity(SocialProvider::Apple, new SocialSubject('same')), true, $this->now);

        self::assertCount(2, $account->socialIdentities());
    }

    public function testBanningMarksTheAccountBanned(): void
    {
        $account = $this->passwordAccount();

        $account->ban();
        $account->ban();

        self::assertTrue($account->isBanned());
        self::assertSame(AccountStatus::Banned, $account->status());
    }

    public function testAVerifiedActiveAccountMayLogIn(): void
    {
        $account = $this->passwordAccount();
        $account->verifyEmail($this->now);

        $account->assertCanLogIn();

        $this->addToAssertionCount(1);
    }

    public function testAnUnverifiedAccountMayNotLogIn(): void
    {
        $this->expectException(AuthenticationProblem::class);
        $this->expectExceptionMessage('not been verified');

        $this->passwordAccount()->assertCanLogIn();
    }

    public function testABannedAccountMayNotLogInEvenWhenVerified(): void
    {
        $account = $this->passwordAccount();
        $account->verifyEmail($this->now);
        $account->ban();

        try {
            $account->assertCanLogIn();
            self::fail('A banned account logged in.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('account-banned', $problem->problemSlug());
        }
    }

    public function testReconstitutingDoesNotRecordEvents(): void
    {
        $account = UserAccount::reconstitute(new AccountId(self::ID), new Email('a@example.com'), null, AccountRole::Tower, null, new Locale('en'), $this->now, AccountStatus::Banned, [$this->google()], $this->now, $this->now);

        self::assertSame([], $account->releaseEvents());
        self::assertTrue($account->isBanned());
        self::assertTrue($account->hasSocialIdentity($this->google()));
        self::assertEquals($this->now, $account->passwordChangedAt());
    }
}
