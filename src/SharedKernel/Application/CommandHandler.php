<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

/**
 * Marker for command handlers. Handlers are registered on the command bus through DI
 * (`_instanceof` in shared_kernel.yaml), not with vendor attributes, so the Application layer stays vendor-free.
 * A handler exposes `__invoke(<its Command>): void`.
 */
interface CommandHandler
{
}
