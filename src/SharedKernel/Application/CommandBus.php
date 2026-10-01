<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

interface CommandBus
{
    /**
     * Handles the command synchronously, in one database transaction.
     */
    public function dispatch(Command $command): void;
}
