<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Scheduler;

use App\Authentication\Application\Command\PurgeExpiredTokens;
use App\SharedKernel\Infrastructure\Scheduler\ScheduledTaskProvider;
use Symfony\Component\Scheduler\RecurringMessage;

/**
 * Deletes long-expired tokens once a day. The command is idempotent.
 */
final readonly class PurgeExpiredTokensTask implements ScheduledTaskProvider
{
    public function recurringMessages(): array
    {
        return [RecurringMessage::every('1 day', new PurgeExpiredTokens())];
    }
}
