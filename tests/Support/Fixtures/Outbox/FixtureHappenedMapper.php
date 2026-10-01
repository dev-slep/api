<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Outbox;

use App\SharedKernel\Application\DomainEventMapper;
use App\SharedKernel\Domain\DomainEvent;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;

use function assert;

final readonly class FixtureHappenedMapper implements DomainEventMapper
{
    public function supports(DomainEvent $event): bool
    {
        return $event instanceof FixtureHappened;
    }

    public function map(DomainEvent $event): array
    {
        assert($event instanceof FixtureHappened);

        return [new FixtureIntegrationEvent($event->aggregateId, $event->occurredAt())];
    }
}
