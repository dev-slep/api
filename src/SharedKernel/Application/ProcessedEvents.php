<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

/**
 * Remembers which integration events a subscriber has already processed, so redelivered events are ignored.
 * Each module has its own `<schema>.processed_event` table and implementation.
 */
interface ProcessedEvents
{
    public function wasProcessed(string $subscriber, string $eventId): bool;

    public function markProcessed(string $subscriber, string $eventId): void;
}
