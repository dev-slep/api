<?php

declare(strict_types=1);

namespace App\Authentication\Contract\Event;

use App\SharedKernel\Contract\UserId;
use DateTimeImmutable;

final readonly class UserRegisteredV1 extends AuthenticationEvent
{
    public function __construct(
        string $eventId,
        DateTimeImmutable $occurredAt,
        public UserId $accountId,
        public string $email,
        public string $role,
        public ?string $phone,
        public string $locale,
        public bool $emailVerified,
    ) {
        parent::__construct($eventId, $occurredAt);
    }

    public function eventName(): string
    {
        return 'authentication.user_registered.v1';
    }
}
