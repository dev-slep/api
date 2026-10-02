<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication\Repository;

use App\Authentication\Domain\Event\TwoFactorEnabled;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Model\TwoFactorSecret;
use App\Authentication\Domain\Model\TwoFactorSecretId;
use App\Authentication\Domain\Repository\TwoFactorSecretRepository;
use App\SharedKernel\Infrastructure\Messaging\CollectedAggregateEvents;
use App\Tests\Support\RepositoryContractTestCase;
use DateTimeImmutable;
use LogicException;

use function sprintf;

/**
 * @extends RepositoryContractTestCase<TwoFactorSecretRepository>
 */
abstract class TwoFactorSecretRepositoryContract extends RepositoryContractTestCase
{
    protected CollectedAggregateEvents $collector;
    protected TwoFactorSecretRepository $repository;

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

    protected function uuid(int $n): string
    {
        return sprintf('01900000-0000-7000-8000-%012d', $n);
    }

    protected function secret(int $n = 1, int $account = 200): TwoFactorSecret
    {
        return TwoFactorSecret::enrol(new TwoFactorSecretId($this->uuid($n)), new AccountId($this->uuid($account)), 'encrypted-'.$n);
    }

    public function testASavedSecretIsFoundByAccount(): void
    {
        $this->repository->save($this->secret());
        $this->forget();

        $found = $this->repository->findByAccount(new AccountId($this->uuid(200)));

        self::assertNotNull($found);
        self::assertSame($this->uuid(1), $found->id()->toString());
        self::assertSame('encrypted-1', $found->encryptedSecret());
        self::assertFalse($found->isConfirmed());
        self::assertSame([], $found->recoveryCodeHashes());
        self::assertNull($found->lastUsedStep());
        self::assertNull($this->repository->findByAccount(new AccountId($this->uuid(201))));
    }

    public function testConfirmationRecoveryCodesAndStepsArePersisted(): void
    {
        $this->repository->save($this->secret());
        $this->forget();
        $secret = $this->repository->findByAccount(new AccountId($this->uuid(200))) ?? throw new LogicException();
        $codes = [new TokenHash(hash('sha256', 'a')), new TokenHash(hash('sha256', 'b'))];

        $secret->confirm($codes, 5_000_000_000, new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $this->repository->save($secret);
        $this->forget();

        $reloaded = $this->repository->findByAccount(new AccountId($this->uuid(200))) ?? throw new LogicException();
        self::assertTrue($reloaded->isConfirmed());
        self::assertEquals(new DateTimeImmutable('2026-01-01T12:00:00+00:00'), $reloaded->confirmedAt());
        self::assertSame(5_000_000_000, $reloaded->lastUsedStep(), 'time steps are stored as 64-bit integers');
        self::assertTrue($reloaded->hasRecoveryCode($codes[0]));

        self::assertTrue($reloaded->useRecoveryCode($codes[0]));
        $reloaded->acceptStep(5_000_000_001);
        $this->repository->save($reloaded);
        $this->forget();

        $again = $this->repository->findByAccount(new AccountId($this->uuid(200)));
        self::assertFalse($again?->hasRecoveryCode($codes[0]), 'a used recovery code stays used');
        self::assertTrue($again->hasRecoveryCode($codes[1]));
        self::assertSame(5_000_000_001, $again->lastUsedStep());
    }

    public function testConfirmingRegistersTheEventForPublishing(): void
    {
        $secret = $this->secret();
        $this->repository->save($secret);
        $this->forget();
        $this->collector->releaseEvents();

        $secret->confirm([], 1, new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $this->repository->save($secret);
        $this->forget();

        $events = $this->collector->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TwoFactorEnabled::class, $events[0]);
    }

    public function testDeletingAllowsANewEnrolmentForTheSameAccount(): void
    {
        $first = $this->secret(1);
        $this->repository->save($first);
        $this->forget();

        $this->repository->delete($first);

        $this->forget();
        self::assertNull($this->repository->findByAccount(new AccountId($this->uuid(200))));
        $this->repository->save($this->secret(2));
        $this->forget();

        self::assertSame($this->uuid(2), $this->repository->findByAccount(new AccountId($this->uuid(200)))?->id()->toString());
    }

    public function testDeletingASecretThatIsGoneIsHarmless(): void
    {
        $secret = $this->secret();

        $this->repository->delete($secret);

        $this->forget();

        $this->addToAssertionCount(1);
    }
}
