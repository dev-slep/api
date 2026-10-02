<?php

declare(strict_types=1);

namespace App\Authentication\Contract\Event;

use App\SharedKernel\Contract\UserId;
use DateTimeImmutable;

final readonly class RefreshTokenReuseDetectedV1 extends AuthenticationEvent
{
    public function __construct(
        string $eventId,
        DateTimeImmutable $occurredAt,
        public UserId $accountId,
        public string $familyId,
        public ?string $ip,
        public ?string $userAgent,
    ) {
        parent::__construct($eventId, $occurredAt);
    }

    public function eventName(): string
    {
        return 'authentication.refresh_token_reuse_detected.v1';
    }
}
