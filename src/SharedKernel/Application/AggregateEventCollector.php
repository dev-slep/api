<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

use App\SharedKernel\Domain\AggregateRoot;

interface AggregateEventCollector
{
    /**
     * Registers an aggregate saved during the current command, so that its recorded
     * domain events are released and published (outbox) when the command succeeds.
     */
    public function collect(AggregateRoot $aggregate): void;
}
