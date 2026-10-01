<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

use App\SharedKernel\Contract\IntegrationEvent;

/**
 * Makes a subscriber idempotent: its work and the "processed" marker are committed in one transaction,
 * so a redelivered event is skipped and a crash in between leaves neither.
 */
final readonly class IdempotentHandling
{
    public function __construct(
        private ProcessedEvents $processedEvents,
        private Transaction $transaction,
    ) {
    }

    /**
     * @param callable(): void $work
     *
     * @return bool true when the work ran, false when the event had already been processed
     */
    public function handle(string $subscriber, IntegrationEvent $event, callable $work): bool
    {
        return $this->transaction->run(function () use ($subscriber, $event, $work): bool {
            if ($this->processedEvents->wasProcessed($subscriber, $event->eventId())) {
                return false;
            }

            $work();
            $this->processedEvents->markProcessed($subscriber, $event->eventId());

            return true;
        });
    }
}
