<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $events = [];

    protected function recordThat(DomainEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Returns the recorded events and clears them.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
