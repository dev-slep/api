<?php

declare(strict_types=1);

namespace App\Authentication\Contract\Event;

use App\SharedKernel\Contract\IntegrationEvent;
use DateTimeImmutable;

/**
 * Base of the Authentication module's integration events. All of them are version 1 and carry no secrets
 * (no passwords, tokens, codes or hashes), so subscribers such as Audit can store them as they are.
 */
abstract readonly class AuthenticationEvent implements IntegrationEvent
{
    public function __construct(
        private string $eventId,
        private DateTimeImmutable $occurredAt,
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
