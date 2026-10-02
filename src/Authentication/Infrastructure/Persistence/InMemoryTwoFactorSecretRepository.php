<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\TwoFactorSecret;
use App\Authentication\Domain\Repository\TwoFactorSecretRepository;
use App\SharedKernel\Application\AggregateEventCollector;

/**
 * Used by application tests, which never touch the database.
 */
final class InMemoryTwoFactorSecretRepository implements TwoFactorSecretRepository
{
    /** @var array<string, TwoFactorSecret> keyed by account id */
    private array $secrets = [];

    public function __construct(private readonly AggregateEventCollector $collector)
    {
    }

    public function findByAccount(AccountId $accountId): ?TwoFactorSecret
    {
        return $this->secrets[$accountId->toString()] ?? null;
    }

    public function save(TwoFactorSecret $secret): void
    {
        $this->secrets[$secret->accountId()->toString()] = $secret;
        $this->collector->collect($secret);
    }

    public function delete(TwoFactorSecret $secret): void
    {
        unset($this->secrets[$secret->accountId()->toString()]);
    }
}
