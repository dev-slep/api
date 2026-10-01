<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

interface QueryBus
{
    /**
     * Handles the query synchronously and returns the handler's result.
     */
    public function ask(Query $query): mixed;
}
