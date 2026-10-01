<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Application\AggregateEventCollector;
use App\SharedKernel\Domain\AggregateRoot;
use App\SharedKernel\Domain\DomainEvent;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Remembers the aggregates saved by the running command and hands out their domain events for the outbox.
 * Reset between requests and between worker messages, so no state leaks.
 */
final class CollectedAggregateEvents implements AggregateEventCollector, ResetInterface
{
    /** @var array<int, AggregateRoot> */
    private array $aggregates = [];

    public function collect(AggregateRoot $aggregate): void
    {
        $this->aggregates[spl_object_id($aggregate)] = $aggregate;
    }

    /**
     * Releases (and clears) the recorded domain events of every collected aggregate, in collection order.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = [];
        foreach ($this->aggregates as $aggregate) {
            array_push($events, ...$aggregate->releaseEvents());
        }
        $this->aggregates = [];

        return $events;
    }

    public function reset(): void
    {
        $this->aggregates = [];
    }
}
