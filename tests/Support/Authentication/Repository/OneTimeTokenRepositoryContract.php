<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication\Repository;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenId;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use App\Tests\Support\RepositoryContractTestCase;
use DateInterval;
use DateTimeImmutable;
use LogicException;

use function sprintf;

/**
 * @extends RepositoryContractTestCase<OneTimeTokenRepository>
 */
abstract class OneTimeTokenRepositoryContract extends RepositoryContractTestCase
{
    protected OneTimeTokenRepository $repository;
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

    protected function token(int $n, int $account = 200, OneTimeTokenPurpose $purpose = OneTimeTokenPurpose::EmailVerification, string $lifetime = 'PT1H'): OneTimeToken
    {
        return OneTimeToken::issue(new OneTimeTokenId($this->uuid($n)), new AccountId($this->uuid($account)), $purpose, $this->hash('token-'.$n), $this->now, new DateInterval($lifetime));
    }

    public function testASavedTokenIsFoundByPurposeAndHash(): void
    {
        $this->repository->save($this->token(1));
        $this->forget();

        $found = $this->repository->findByHash(OneTimeTokenPurpose::EmailVerification, $this->hash('token-1'));

        self::assertNotNull($found);
        self::assertSame($this->uuid(1), $found->id()->toString());
        self::assertSame($this->uuid(200), $found->accountId()->toString());
        self::assertEquals($this->now->modify('+1 hour'), $found->expiresAt());
        self::assertNull($found->usedAt());
        self::assertNull($this->repository->findByHash(OneTimeTokenPurpose::PasswordReset, $this->hash('token-1')), 'the purpose is part of the key');
        self::assertNull($this->repository->findByHash(OneTimeTokenPurpose::EmailVerification, $this->hash('other')));
    }

    public function testUsingATokenIsPersisted(): void
    {
        $this->repository->save($this->token(1));
        $this->forget();
        $token = $this->repository->findByHash(OneTimeTokenPurpose::EmailVerification, $this->hash('token-1')) ?? throw new LogicException();

        $token->consume($this->now->modify('+5 minutes'));
        $this->repository->save($token);
        $this->forget();

        $reloaded = $this->repository->findByHash(OneTimeTokenPurpose::EmailVerification, $this->hash('token-1'));
        self::assertEquals($this->now->modify('+5 minutes'), $reloaded?->usedAt());
        self::assertFalse($reloaded?->isUsable($this->now->modify('+6 minutes')));
    }

    public function testInvalidatingReplacesOnlyTheAccountsUsableTokensOfThatPurpose(): void
    {
        $this->repository->save($this->token(1, 200, OneTimeTokenPurpose::EmailVerification));
        $this->forget();
        $this->repository->save($this->token(2, 200, OneTimeTokenPurpose::PasswordReset));
        $this->forget();
        $this->repository->save($this->token(3, 201, OneTimeTokenPurpose::EmailVerification));
        $this->forget();
        $used = $this->token(4, 200, OneTimeTokenPurpose::EmailVerification);
        $used->consume($this->now);
        $this->repository->save($used);
        $this->forget();

        $this->repository->invalidateAllFor(new AccountId($this->uuid(200)), OneTimeTokenPurpose::EmailVerification, $this->now->modify('+1 minute'));

        $this->forget();

        $usable = static fn (self $self, string $purpose, int $n): ?bool => $self->repository->findByHash(OneTimeTokenPurpose::from($purpose), $self->hash('token-'.$n))?->isUsable($self->now->modify('+2 minutes'));
        self::assertFalse($usable($this, 'EMAIL_VERIFICATION', 1));
        self::assertTrue($usable($this, 'PASSWORD_RESET', 2));
        self::assertTrue($usable($this, 'EMAIL_VERIFICATION', 3));
        self::assertNull($this->repository->findByHash(OneTimeTokenPurpose::EmailVerification, $this->hash('token-4'))?->invalidatedAt(), 'a used token is left as it was');
    }

    public function testExpiredTokensAreDeletedStrictlyBeforeTheGivenMoment(): void
    {
        $this->repository->save($this->token(1, 200, OneTimeTokenPurpose::EmailVerification, 'PT1H'));
        $this->forget();
        $this->repository->save($this->token(2, 200, OneTimeTokenPurpose::PasswordReset, 'PT2H'));
        $this->forget();

        self::assertSame(0, $this->repository->deleteExpiredBefore($this->now->modify('+1 hour')));
        self::assertSame(1, $this->repository->deleteExpiredBefore($this->now->modify('+1 hour +1 second')));
        self::assertNull($this->repository->findByHash(OneTimeTokenPurpose::EmailVerification, $this->hash('token-1')));
        self::assertNotNull($this->repository->findByHash(OneTimeTokenPurpose::PasswordReset, $this->hash('token-2')));
    }
}
