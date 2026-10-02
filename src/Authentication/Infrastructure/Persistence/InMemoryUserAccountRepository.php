<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\AggregateEventCollector;

/**
 * Used by application tests, which never touch the database.
 */
final class InMemoryUserAccountRepository implements UserAccountRepository
{
    /** @var array<string, UserAccount> */
    private array $accounts = [];

    public function __construct(private readonly AggregateEventCollector $collector)
    {
    }

    public function findById(AccountId $id): ?UserAccount
    {
        return $this->accounts[$id->toString()] ?? null;
    }

    public function findByEmail(Email $email): ?UserAccount
    {
        foreach ($this->accounts as $account) {
            if ($account->email()->equals($email)) {
                return $account;
            }
        }

        return null;
    }

    public function findBySocialIdentity(SocialIdentity $identity): ?UserAccount
    {
        foreach ($this->accounts as $account) {
            if ($account->hasSocialIdentity($identity)) {
                return $account;
            }
        }

        return null;
    }

    public function save(UserAccount $account): void
    {
        $existing = $this->findByEmail($account->email());
        if (null !== $existing && !$existing->id()->equals($account->id())) {
            throw AuthenticationProblem::emailAlreadyRegistered();
        }

        $this->accounts[$account->id()->toString()] = $account;
        $this->collector->collect($account);
    }
}
