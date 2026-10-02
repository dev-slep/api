<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\SharedKernel\Application\Command;
use App\SharedKernel\Application\CommandBus;
use Throwable;

/**
 * A command bus for unit tests: remembers what was dispatched and answers with a given result or failure.
 */
final class RecordingCommandBus implements CommandBus
{
    /** @var list<Command> */
    public array $dispatched = [];

    public function __construct(
        private readonly mixed $result = null,
        private readonly ?Throwable $failure = null,
    ) {
    }

    public function dispatch(Command $command): mixed
    {
        $this->dispatched[] = $command;
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return $this->result;
    }
}
