<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Persistence;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\RefreshTokenId;
use App\Authentication\Infrastructure\Persistence\DoctrineRefreshTokenRepository;
use App\Authentication\Infrastructure\Persistence\Mapper\RefreshTokenMapper;
use App\Tests\Support\Authentication\Repository\RefreshTokenRepositoryContract;
use App\Tests\Support\Authentication\Repository\UsesDoctrine;
use DateInterval;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(DoctrineRefreshTokenRepository::class)]
final class DoctrineRefreshTokenRepositoryTest extends RefreshTokenRepositoryContract
{
    use UsesDoctrine;

    protected function createRepository(): object
    {
        return new DoctrineRefreshTokenRepository($this->entityManager(), new RefreshTokenMapper());
    }

    public function testTheVersionCountsEveryChange(): void
    {
        $this->repository->save($this->token(1));
        $this->forget();
        $loaded = $this->repository->findByHash($this->hash('token-1')) ?? throw new LogicException();
        self::assertSame(1, $loaded->version());

        $loaded->revoke($this->now);
        $this->repository->save($loaded);
        $this->forget();

        self::assertSame(2, $this->repository->findByHash($this->hash('token-1'))?->version());
    }

    public function testOfTwoParallelRotationsOfTheSameTokenOnlyTheFirstWins(): void
    {
        $this->repository->save($this->token(1));
        $this->forget();
        $first = $this->repository->findByHash($this->hash('token-1')) ?? throw new LogicException();
        $this->forget();
        $second = $this->repository->findByHash($this->hash('token-1')) ?? throw new LogicException();

        $next = $first->rotate(new RefreshTokenId($this->uuid(2)), $this->hash('token-2'), $this->now, new DateInterval('P30D'));
        $this->repository->save($first);
        $this->repository->save($next);
        $this->forget();

        $rival = $second->rotate(new RefreshTokenId($this->uuid(3)), $this->hash('token-3'), $this->now, new DateInterval('P30D'));
        try {
            $this->repository->save($second);
            self::fail('The second rotation overwrote the first.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('token-invalid', $problem->problemSlug());
        }
        self::assertNull($this->repository->findByHash($this->hash('token-3')), 'the losing rotation left nothing behind');
        self::assertSame($this->uuid(2), $this->repository->findByHash($this->hash('token-1'))?->replacedBy()?->toString());
    }

    public function testTheHashIsUnique(): void
    {
        $this->repository->save($this->token(1));
        $this->forget();

        $this->expectException(Throwable::class);
        $this->repository->save(\App\Authentication\Domain\Model\RefreshToken::issue(new RefreshTokenId($this->uuid(9)), new \App\Authentication\Domain\Model\TokenFamilyId($this->uuid(100)), new \App\Authentication\Domain\Model\AccountId($this->uuid(200)), $this->hash('token-1'), $this->now, new DateInterval('P30D')));
    }
}
