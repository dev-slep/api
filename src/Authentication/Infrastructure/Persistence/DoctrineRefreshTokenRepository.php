<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\RefreshToken;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\Authentication\Infrastructure\Persistence\Entity\RefreshTokenRecord;
use App\Authentication\Infrastructure\Persistence\Mapper\RefreshTokenMapper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

final readonly class DoctrineRefreshTokenRepository implements RefreshTokenRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenMapper $mapper,
    ) {
    }

    public function findByHash(TokenHash $hash): ?RefreshToken
    {
        $record = $this->entityManager->getRepository(RefreshTokenRecord::class)->findOneBy(['hash' => $hash->value()]);

        return null === $record ? null : $this->mapper->toDomain($record);
    }

    public function save(RefreshToken $token): void
    {
        $record = $this->entityManager->find(RefreshTokenRecord::class, $token->id()->toString());
        if (null !== $record && $record->version !== $token->version()) {
            // Someone else changed this token since it was loaded (e.g. a parallel refresh): refuse instead of overwriting
            throw AuthenticationProblem::tokenInvalid();
        }
        $this->entityManager->persist($this->mapper->apply($token, $record ?? new RefreshTokenRecord()));

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw AuthenticationProblem::tokenInvalid();
        }
    }

    public function revokeFamily(TokenFamilyId $familyId, DateTimeImmutable $now): void
    {
        $this->entityManager->createQuery('UPDATE '.RefreshTokenRecord::class.' t SET t.revokedAt = :now WHERE t.familyId = :family AND t.revokedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('family', $familyId->toString())
            ->execute();
    }

    public function revokeAllForAccount(AccountId $accountId, DateTimeImmutable $now): void
    {
        $this->entityManager->createQuery('UPDATE '.RefreshTokenRecord::class.' t SET t.revokedAt = :now WHERE t.accountId = :account AND t.revokedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('account', $accountId->toString())
            ->execute();
    }

    public function deleteExpiredBefore(DateTimeImmutable $moment): int
    {
        return $this->entityManager->createQuery('DELETE FROM '.RefreshTokenRecord::class.' t WHERE t.expiresAt < :moment')
            ->setParameter('moment', $moment)
            ->execute();
    }
}
