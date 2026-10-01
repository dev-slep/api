<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

use Throwable;

/**
 * Hook called by the command bus for every command; the Audit module implements it later.
 */
interface CommandAuditor
{
    public function recordSuccess(Command $command): void;

    public function recordFailure(Command $command, Throwable $failure): void;
}
