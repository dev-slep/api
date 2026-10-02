<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Persistence;

use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use App\Authentication\Infrastructure\Persistence\DoctrineUserAccountRepository;
use App\Authentication\Infrastructure\Persistence\Mapper\UserAccountMapper;
use App\Tests\Support\Authentication\Repository\UserAccountRepositoryContract;
use App\Tests\Support\Authentication\Repository\UsesDoctrine;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(DoctrineUserAccountRepository::class)]
final class DoctrineUserAccountRepositoryTest extends UserAccountRepositoryContract
{
    use UsesDoctrine;

    protected function createRepository(): object
    {
        return new DoctrineUserAccountRepository($this->entityManager(), new UserAccountMapper(), $this->collector);
    }

    public function testTheDatabaseKeepsEmailsUniqueEvenWhenTwoWritersRace(): void
    {
        $this->repository->save($this->account(1, 'ana@example.com'));
        $this->forget();

        // The same email under another id, written behind the repository's own check
        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->entityManager()->getConnection()->executeStatement(
            "INSERT INTO authentication.user_account (id, email, role, locale, status, registered_at) VALUES (:id, 'ana@example.com', 'DRIVER', 'en', 'ACTIVE', NOW())",
            ['id' => $this->id(2)->toString()],
        );
    }

    public function testAnIdentityBelongsToExactlyOneAccount(): void
    {
        $identity = new SocialIdentity(SocialProvider::Google, new SocialSubject('shared'));
        $first = $this->account(1, 'ana@example.com');
        $first->linkSocialIdentity($identity, true, new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
        $this->repository->save($first);
        $this->forget();

        $second = $this->account(2, 'bob@example.com');
        $second->linkSocialIdentity($identity, true, new DateTimeImmutable('2026-01-02T00:00:00+00:00'));

        $this->expectException(Throwable::class);
        $this->repository->save($second);
    }
}
