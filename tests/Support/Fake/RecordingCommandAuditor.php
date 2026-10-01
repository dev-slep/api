<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\SharedKernel\Application\Command;
use App\SharedKernel\Application\CommandAuditor;
use Throwable;

final class RecordingCommandAuditor implements CommandAuditor
{
    /** @var list<array{outcome: string, command: Command, failure: Throwable|null}> */
    public array $entries = [];

    public function recordSuccess(Command $command): void
    {
        $this->entries[] = ['outcome' => 'success', 'command' => $command, 'failure' => null];
    }

    public function recordFailure(Command $command, Throwable $failure): void
    {
        $this->entries[] = ['outcome' => 'failure', 'command' => $command, 'failure' => $failure];
    }
}
