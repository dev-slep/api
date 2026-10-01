<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Outbox;

use App\SharedKernel\Domain\DomainEvent;
use DateTimeImmutable;

final readonly class FixtureHappened implements DomainEvent
{
    public function __construct(
        public string $aggregateId,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
