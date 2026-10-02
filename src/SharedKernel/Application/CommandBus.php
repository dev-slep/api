<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

interface CommandBus
{
    /**
     * Handles the command synchronously, in one database transaction.
     *
     * @return mixed the handler's return value (most handlers return nothing); credential and token flows use it
     *               to hand results back to the caller. A handler that must keep its changes even when the
     *               operation is refused (e.g. a failed login that records an event) returns a failure result
     *               instead of throwing, because an exception rolls the transaction back
     */
    public function dispatch(Command $command): mixed;
}
