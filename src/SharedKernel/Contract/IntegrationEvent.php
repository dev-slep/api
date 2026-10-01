<?php

declare(strict_types=1);

namespace App\SharedKernel\Contract;

use DateTimeImmutable;

/**
 * The public, versioned form of a domain event, published for other modules to consume.
 */
interface IntegrationEvent
{
    public function eventId(): string;

    /** Stable name including the version suffix, e.g. "tow_request.posted.v1". */
    public function eventName(): string;

    public function version(): int;

    public function occurredAt(): DateTimeImmutable;
}
