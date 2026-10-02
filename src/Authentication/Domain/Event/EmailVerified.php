<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Event;

use App\Authentication\Domain\Model\AccountId;
use App\SharedKernel\Domain\DomainEvent;
use DateTimeImmutable;

final readonly class EmailVerified implements DomainEvent
{
    public function __construct(
        public AccountId $accountId,
        public DateTimeImmutable $at,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
