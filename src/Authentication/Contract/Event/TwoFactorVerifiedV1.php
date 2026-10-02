<?php

declare(strict_types=1);

namespace App\Authentication\Contract\Event;

use App\SharedKernel\Contract\UserId;
use DateTimeImmutable;

final readonly class TwoFactorVerifiedV1 extends AuthenticationEvent
{
    public function __construct(
        string $eventId,
        DateTimeImmutable $occurredAt,
        public UserId $accountId,
        public string $method,
        public ?string $ip,
        public ?string $userAgent,
    ) {
        parent::__construct($eventId, $occurredAt);
    }

    public function eventName(): string
    {
        return 'authentication.two_factor_verified.v1';
    }
}
