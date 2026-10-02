<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\TwoFactorSecret;
use App\Authentication\Domain\Repository\TwoFactorSecretRepository;
use App\Authentication\Infrastructure\Persistence\Entity\TwoFactorSecretRecord;
use App\Authentication\Infrastructure\Persistence\Mapper\TwoFactorSecretMapper;
use App\SharedKernel\Application\AggregateEventCollector;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTwoFactorSecretRepository implements TwoFactorSecretRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TwoFactorSecretMapper $mapper,
        private AggregateEventCollector $collector,
    ) {
    }

    public function findByAccount(AccountId $accountId): ?TwoFactorSecret
    {
        $record = $this->entityManager->getRepository(TwoFactorSecretRecord::class)->findOneBy(['accountId' => $accountId->toString()]);

        return null === $record ? null : $this->mapper->toDomain($record);
    }

    public function save(TwoFactorSecret $secret): void
    {
        $record = $this->entityManager->find(TwoFactorSecretRecord::class, $secret->id()->toString()) ?? new TwoFactorSecretRecord();
        $this->entityManager->persist($this->mapper->apply($secret, $record));
        $this->entityManager->flush();

        $this->collector->collect($secret);
    }

    public function delete(TwoFactorSecret $secret): void
    {
        $record = $this->entityManager->find(TwoFactorSecretRecord::class, $secret->id()->toString());
        if (null !== $record) {
            $this->entityManager->remove($record);
            // Flushed now: Doctrine runs inserts before deletes, and the account may get a new secret right away
            $this->entityManager->flush();
        }
    }
}
