<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\Authentication\Infrastructure\Persistence\Entity\SocialIdentityRecord;
use App\Authentication\Infrastructure\Persistence\Entity\UserAccountRecord;
use App\Authentication\Infrastructure\Persistence\Mapper\UserAccountMapper;
use App\SharedKernel\Application\AggregateEventCollector;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserAccountRepository implements UserAccountRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserAccountMapper $mapper,
        private AggregateEventCollector $collector,
    ) {
    }

    public function findById(AccountId $id): ?UserAccount
    {
        $record = $this->entityManager->find(UserAccountRecord::class, $id->toString());

        return null === $record ? null : $this->mapper->toDomain($record);
    }

    public function findByEmail(Email $email): ?UserAccount
    {
        $record = $this->entityManager->getRepository(UserAccountRecord::class)->findOneBy(['email' => $email->toString()]);

        return null === $record ? null : $this->mapper->toDomain($record);
    }

    public function findBySocialIdentity(SocialIdentity $identity): ?UserAccount
    {
        $record = $this->entityManager->find(SocialIdentityRecord::class, [
            'provider' => $identity->provider->value,
            'subject' => $identity->subject->toString(),
        ]);

        return null === $record ? null : $this->mapper->toDomain($record->account);
    }

    public function save(UserAccount $account): void
    {
        $record = $this->entityManager->find(UserAccountRecord::class, $account->id()->toString()) ?? new UserAccountRecord();
        $this->entityManager->persist($this->mapper->apply($account, $record));

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw AuthenticationProblem::emailAlreadyRegistered();
        }

        $this->collector->collect($account);
    }
}
