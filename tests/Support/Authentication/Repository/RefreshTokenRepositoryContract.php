<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication\Repository;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\RefreshToken;
use App\Authentication\Domain\Model\RefreshTokenId;
use App\Authentication\Domain\Model\RefreshTokenStatus;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\Tests\Support\RepositoryContractTestCase;
use DateInterval;
use DateTimeImmutable;
use LogicException;

use function sprintf;

/**
 * @extends RepositoryContractTestCase<RefreshTokenRepository>
 */
abstract class RefreshTokenRepositoryContract extends RepositoryContractTestCase
{
    protected RefreshTokenRepository $repository;
    protected DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
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

    protected function hash(string $seed): TokenHash
    {
        return new TokenHash(hash('sha256', $seed));
    }

    protected function token(int $n, int $family = 100, int $account = 200, ?DateTimeImmutable $issuedAt = null, string $lifetime = 'P30D'): RefreshToken
    {
        return RefreshToken::issue(new RefreshTokenId($this->uuid($n)), new TokenFamilyId($this->uuid($family)), new AccountId($this->uuid($account)), $this->hash('token-'.$n), $issuedAt ?? $this->now, new DateInterval($lifetime));
    }

    public function testASavedTokenIsFoundByItsHash(): void
    {
        $this->repository->save($this->token(1));
        $this->forget();

        $found = $this->repository->findByHash($this->hash('token-1'));

        self::assertNotNull($found);
        self::assertSame($this->uuid(1), $found->id()->toString());
        self::assertSame($this->uuid(100), $found->familyId()->toString());
        self::assertSame($this->uuid(200), $found->accountId()->toString());
        self::assertEquals($this->now, $found->issuedAt());
        self::assertEquals($this->now->modify('+30 days'), $found->expiresAt());
        self::assertNull($found->rotatedAt());
        self::assertNull($found->revokedAt());
        self::assertNull($found->replacedBy());
        self::assertNull($this->repository->findByHash($this->hash('unknown')));
    }

    public function testRotationIsPersisted(): void
    {
        $this->repository->save($this->token(1));
        $this->forget();
        $current = $this->repository->findByHash($this->hash('token-1')) ?? throw new LogicException();
        $later = $this->now->modify('+1 day');

        $next = $current->rotate(new RefreshTokenId($this->uuid(2)), $this->hash('token-2'), $later, new DateInterval('P30D'));
        $this->repository->save($current);
        $this->forget();
        $this->repository->save($next);
        $this->forget();

        $old = $this->repository->findByHash($this->hash('token-1'));
        $new = $this->repository->findByHash($this->hash('token-2'));
        self::assertSame(RefreshTokenStatus::Rotated, $old?->status($later));
        self::assertSame($this->uuid(2), $old->replacedBy()?->toString());
        self::assertSame($this->uuid(100), $new?->familyId()->toString());
        self::assertSame(RefreshTokenStatus::Usable, $new->status($later));
    }

    public function testRevokingAFamilyLeavesOtherFamiliesAlone(): void
    {
        $this->repository->save($this->token(1, 100));
        $this->forget();
        $this->repository->save($this->token(2, 100));
        $this->forget();
        $this->repository->save($this->token(3, 101));
        $this->forget();

        $this->repository->revokeFamily(new TokenFamilyId($this->uuid(100)), $this->now);

        $this->forget();

        self::assertSame(RefreshTokenStatus::Revoked, $this->repository->findByHash($this->hash('token-1'))?->status($this->now));
        self::assertSame(RefreshTokenStatus::Revoked, $this->repository->findByHash($this->hash('token-2'))?->status($this->now));
        self::assertSame(RefreshTokenStatus::Usable, $this->repository->findByHash($this->hash('token-3'))?->status($this->now));
    }

    public function testRevokingAFamilyKeepsTheEarlierRevocationTime(): void
    {
        $this->repository->save($this->token(1, 100));
        $this->forget();
        $this->repository->revokeFamily(new TokenFamilyId($this->uuid(100)), $this->now);
        $this->forget();

        $this->repository->revokeFamily(new TokenFamilyId($this->uuid(100)), $this->now->modify('+1 hour'));

        $this->forget();

        self::assertEquals($this->now, $this->repository->findByHash($this->hash('token-1'))?->revokedAt());
    }

    public function testRevokingAnAccountEndsEveryFamilyOfThatAccountOnly(): void
    {
        $this->repository->save($this->token(1, 100, 200));
        $this->forget();
        $this->repository->save($this->token(2, 101, 200));
        $this->forget();
        $this->repository->save($this->token(3, 102, 201));
        $this->forget();

        $this->repository->revokeAllForAccount(new AccountId($this->uuid(200)), $this->now);

        $this->forget();

        self::assertSame(RefreshTokenStatus::Revoked, $this->repository->findByHash($this->hash('token-1'))?->status($this->now));
        self::assertSame(RefreshTokenStatus::Revoked, $this->repository->findByHash($this->hash('token-2'))?->status($this->now));
        self::assertSame(RefreshTokenStatus::Usable, $this->repository->findByHash($this->hash('token-3'))?->status($this->now));
    }

    public function testRevokingWhenThereIsNothingToRevokeIsHarmless(): void
    {
        $this->repository->revokeFamily(new TokenFamilyId($this->uuid(999)), $this->now);
        $this->forget();
        $this->repository->revokeAllForAccount(new AccountId($this->uuid(999)), $this->now);
        $this->forget();

        $this->addToAssertionCount(1);
    }

    public function testExpiredTokensAreDeletedExactlyBeforeTheGivenMoment(): void
    {
        $this->repository->save($this->token(1, 100, 200, $this->now, 'P1D'));
        $this->forget();
        $this->repository->save($this->token(2, 101, 200, $this->now, 'P2D'));
        $this->forget();
        $this->repository->save($this->token(3, 102, 200, $this->now, 'P3D'));
        $this->forget();

        $deleted = $this->repository->deleteExpiredBefore($this->now->modify('+2 days'));

        self::assertSame(1, $deleted, 'only the token that expired strictly before the moment goes');
        self::assertNull($this->repository->findByHash($this->hash('token-1')));
        self::assertNotNull($this->repository->findByHash($this->hash('token-2')));
        self::assertNotNull($this->repository->findByHash($this->hash('token-3')));
        self::assertSame(0, $this->repository->deleteExpiredBefore($this->now->modify('+2 days')), 'a second run deletes nothing');
    }
}
