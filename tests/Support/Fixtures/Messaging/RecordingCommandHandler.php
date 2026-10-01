<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Messaging;

use App\SharedKernel\Application\CommandHandler;

final readonly class RecordingCommandHandler implements CommandHandler
{
    public function __construct(private RecordedMessages $recorded)
    {
    }

    public function __invoke(RecordingCommand $command): void
    {
        $this->recorded->handled[] = $command;
    }
}
