<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Messenger wraps whatever a handler throws in a HandlerFailedException; callers of the buses
 * (and the error handling) must see the original exception.
 */
trait UnwrapsHandlerFailure
{
    private function unwrap(HandlerFailedException $failure): Throwable
    {
        return array_values($failure->getWrappedExceptions())[0] ?? $failure;
    }
}
