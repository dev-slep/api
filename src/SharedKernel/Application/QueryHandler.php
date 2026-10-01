<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

/**
 * Marker for query handlers, registered on the query bus through DI.
 * A handler exposes `__invoke(<its Query>): mixed` and returns the result.
 */
interface QueryHandler
{
}
