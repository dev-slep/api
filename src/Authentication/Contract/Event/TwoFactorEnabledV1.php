<?php

declare(strict_types=1);

namespace App\Authentication\Contract\Event;

use App\SharedKernel\Contract\UserId;
use DateTimeImmutable;

final readonly class TwoFactorEnabledV1 extends AuthenticationEvent
{
    public function __construct(
        string $eventId,
        DateTimeImmutable $occurredAt,
        public UserId $accountId,
    ) {
        parent::__construct($eventId, $occurredAt);
    }

    public function eventName(): string
    {
        return 'authentication.two_factor_enabled.v1';
    }
}
