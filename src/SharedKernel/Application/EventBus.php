<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

use App\SharedKernel\Contract\IntegrationEvent;

interface EventBus
{
    /**
     * Publishes integration events for asynchronous delivery to their subscribers.
     */
    public function publish(IntegrationEvent ...$events): void;
}
