<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Messaging;

use RuntimeException;

/**
 * Always fails: proves that a message ends up in the failed transport after its retries.
 * Registered on the event bus in the test environment only (see shared_kernel.yaml).
 */
final class FailingEventSubscriber
{
    public function __invoke(FixtureIntegrationEvent $event): void
    {
        throw new RuntimeException('Subscriber failed on purpose.');
    }
}
