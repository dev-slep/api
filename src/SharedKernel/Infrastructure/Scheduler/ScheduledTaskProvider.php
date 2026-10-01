<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;

/**
 * Implemented by modules (in their `Infrastructure/Scheduler/`) to contribute recurring tasks to the
 * single `default` schedule. Tagged `app.scheduled_task` automatically. Tasks must be idempotent.
 */
interface ScheduledTaskProvider
{
    /**
     * @return list<RecurringMessage> recurring messages (usually commands) dispatched on the command bus
     */
    public function recurringMessages(): array;
}
