<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication\Repository;

use App\Authentication\Domain\Event\UserRegistered;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\PasswordHash;
use App\Authentication\Domain\Model\PhoneNumber;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Infrastructure\Messaging\CollectedAggregateEvents;
use App\Tests\Support\RepositoryContractTestCase;
use DateTimeImmutable;
use LogicException;

use function sprintf;

/**
 * Behaviour every UserAccountRepository must show; run against the in-memory and the Doctrine implementation.
 *
 * @extends RepositoryContractTestCase<UserAccountRepository>
 */
abstract class UserAccountRepositoryContract extends RepositoryContractTestCase
{
    protected CollectedAggregateEvents $collector;
    protected UserAccountRepository $repository;

    protected function setUp(): void
    {
        $this->collector = new CollectedAggregateEvents();
        $this->repository = $this->createRepository();
    }

    /**
     * Called after every write. The Doctrine tests clear the entity manager here, so that the next read really
     * comes from the database; the in-memory tests have nothing to do.
     */
    protected function forget(): void
    {
    }

    protected function id(int $n): AccountId
    {
        return new AccountId(sprintf('01900000-0000-7000-8000-%012d', $n));
    }

    protected function account(int $n = 1, string $email = 'ana@example.com', ?string $phone = '+381641234567'): UserAccount
    {
        return UserAccount::registerWithPassword($this->id($n), new Email($email), new PasswordHash('hash-'.$n), AccountRole::Tower, null === $phone ? null : new PhoneNumber($phone), new Locale('sr_Latn'), new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
    }

    public function testASavedAccountCanBeFoundByIdAndByEmail(): void
    {
        $this->repository->save($this->account());
        $this->forget();

        foreach ([$this->repository->findById($this->id(1)), $this->repository->findByEmail(new Email('ANA@example.com'))] as $found) {
            self::assertNotNull($found);
            self::assertSame($this->id(1)->toString(), $found->id()->toString());
            self::assertSame('ana@example.com', $found->email()->toString());
            self::assertSame('hash-1', $found->passwordHash()?->value());
            self::assertSame(AccountRole::Tower, $found->role());
            self::assertSame('+381641234567', $found->phone()?->toString());
            self::assertSame('sr_Latn', $found->locale()->toString());
            self::assertFalse($found->isEmailVerified());
            self::assertEquals(new DateTimeImmutable('2026-01-01T12:00:00+00:00'), $found->registeredAt());
        }
    }

    public function testOptionalFieldsRoundTripAsNull(): void
    {
        $this->repository->save($this->account(1, 'ana@example.com', null));
        $this->forget();

        $found = $this->repository->findById($this->id(1));

        self::assertNull($found?->phone());
        self::assertNull($found?->emailVerifiedAt());
        self::assertNull($found?->passwordChangedAt());
        self::assertSame([], $found?->socialIdentities());
    }

    public function testUnknownAccountsAreNotFound(): void
    {
        $this->repository->save($this->account());
        $this->forget();

        self::assertNull($this->repository->findById($this->id(2)));
        self::assertNull($this->repository->findByEmail(new Email('nobody@example.com')));
        self::assertNull($this->repository->findBySocialIdentity(new SocialIdentity(SocialProvider::Google, new SocialSubject('nobody'))));
    }

    public function testChangesToASavedAccountArePersisted(): void
    {
        $account = $this->account();
        $this->repository->save($account);
        $this->forget();
        $loaded = $this->repository->findById($this->id(1)) ?? throw new LogicException();
        $when = new DateTimeImmutable('2026-02-01T08:30:00+00:00');

        $loaded->verifyEmail($when);
        $loaded->changePassword(new PasswordHash('new-hash'), $when);
        $loaded->ban();
        $this->repository->save($loaded);
        $this->forget();

        $reloaded = $this->repository->findById($this->id(1));
        self::assertEquals($when, $reloaded?->emailVerifiedAt());
        self::assertSame('new-hash', $reloaded?->passwordHash()?->value());
        self::assertEquals($when, $reloaded->passwordChangedAt());
        self::assertTrue($reloaded->isBanned());
    }

    public function testSocialIdentitiesAreSavedAndFound(): void
    {
        $google = new SocialIdentity(SocialProvider::Google, new SocialSubject('g-1'));
        $apple = new SocialIdentity(SocialProvider::Apple, new SocialSubject('g-1'));
        $account = $this->account();
        $account->linkSocialIdentity($google, true, new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
        $this->repository->save($account);
        $this->forget();

        $byGoogle = $this->repository->findBySocialIdentity($google);
        self::assertSame($this->id(1)->toString(), $byGoogle?->id()->toString());
        self::assertTrue($byGoogle->hasSocialIdentity($google));
        self::assertNull($this->repository->findBySocialIdentity($apple), 'the same subject at another provider is another identity');

        $byGoogle->linkSocialIdentity($apple, false, new DateTimeImmutable('2026-01-03T00:00:00+00:00'));
        $this->repository->save($byGoogle);
        $this->forget();
        self::assertCount(2, $this->repository->findById($this->id(1))?->socialIdentities() ?? []);
        self::assertSame($this->id(1)->toString(), $this->repository->findBySocialIdentity($apple)?->id()->toString());
    }

    public function testTwoAccountsCannotShareAnEmail(): void
    {
        $this->repository->save($this->account(1, 'ana@example.com'));
        $this->forget();

        try {
            $this->repository->save($this->account(2, 'Ana@Example.com'));
            $this->forget();
            self::fail('A duplicate email was saved.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('email-already-registered', $problem->problemSlug());
        }
    }

    public function testSavingTheSameAccountAgainIsNotADuplicate(): void
    {
        $account = $this->account();
        $this->repository->save($account);
        $this->forget();
        $this->repository->save($account);
        $this->forget();

        self::assertNotNull($this->repository->findByEmail(new Email('ana@example.com')));
    }

    public function testSavedAccountsAreRegisteredForEventPublishing(): void
    {
        $this->repository->save($this->account());
        $this->forget();

        $events = $this->collector->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(UserRegistered::class, $events[0]);
    }
}
