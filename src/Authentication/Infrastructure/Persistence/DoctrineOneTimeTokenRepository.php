<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use App\Authentication\Infrastructure\Persistence\Entity\OneTimeTokenRecord;
use App\Authentication\Infrastructure\Persistence\Mapper\OneTimeTokenMapper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineOneTimeTokenRepository implements OneTimeTokenRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OneTimeTokenMapper $mapper,
    ) {
    }

    public function findByHash(OneTimeTokenPurpose $purpose, TokenHash $hash): ?OneTimeToken
    {
        $record = $this->entityManager->getRepository(OneTimeTokenRecord::class)->findOneBy(['purpose' => $purpose->value, 'hash' => $hash->value()]);

        return null === $record ? null : $this->mapper->toDomain($record);
    }

    public function save(OneTimeToken $token): void
    {
        $record = $this->entityManager->find(OneTimeTokenRecord::class, $token->id()->toString()) ?? new OneTimeTokenRecord();
        $this->entityManager->persist($this->mapper->apply($token, $record));
        $this->entityManager->flush();
    }

    public function invalidateAllFor(AccountId $accountId, OneTimeTokenPurpose $purpose, DateTimeImmutable $now): void
    {
        $this->entityManager->createQuery('UPDATE '.OneTimeTokenRecord::class.' t SET t.invalidatedAt = :now WHERE t.accountId = :account AND t.purpose = :purpose AND t.usedAt IS NULL AND t.invalidatedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('account', $accountId->toString())
            ->setParameter('purpose', $purpose->value)
            ->execute();
    }

    public function deleteExpiredBefore(DateTimeImmutable $moment): int
    {
        return $this->entityManager->createQuery('DELETE FROM '.OneTimeTokenRecord::class.' t WHERE t.expiresAt < :moment')
            ->setParameter('moment', $moment)
            ->execute();
    }
}
