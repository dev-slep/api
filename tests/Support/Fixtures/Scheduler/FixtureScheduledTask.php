<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Scheduler;

use App\SharedKernel\Infrastructure\Scheduler\ScheduledTaskProvider;
use App\Tests\Support\Fixtures\Messaging\RecordingCommand;
use Symfony\Component\Scheduler\RecurringMessage;

final readonly class FixtureScheduledTask implements ScheduledTaskProvider
{
    public function recurringMessages(): array
    {
        return [RecurringMessage::every('1 hour', new RecordingCommand('tick'))];
    }
}
