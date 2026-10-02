<?php

declare(strict_types=1);

namespace App\Penalty\Contract\Event;

use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Contract\UserId;
use DateTimeImmutable;

/**
 * Base of the Penalty module's integration events about a sanctioned user.
 */
abstract readonly class PenaltyEvent implements IntegrationEvent
{
    public function __construct(
        private string $eventId,
        private DateTimeImmutable $occurredAt,
        public UserId $userId,
        public string $reason,
    ) {
    }

    final public function eventId(): string
    {
        return $this->eventId;
    }

    final public function version(): int
    {
        return 1;
    }

    final public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
