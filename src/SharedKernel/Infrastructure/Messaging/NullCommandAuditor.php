<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Application\Command;
use App\SharedKernel\Application\CommandAuditor;
use Throwable;

/**
 * Default auditor until the Audit module provides its own implementation.
 */
final readonly class NullCommandAuditor implements CommandAuditor
{
    public function recordSuccess(Command $command): void
    {
    }

    public function recordFailure(Command $command, Throwable $failure): void
    {
    }
}
