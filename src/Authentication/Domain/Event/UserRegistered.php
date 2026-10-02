<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Event;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\PhoneNumber;
use App\SharedKernel\Domain\DomainEvent;
use DateTimeImmutable;

final readonly class UserRegistered implements DomainEvent
{
    public function __construct(
        public AccountId $accountId,
        public Email $email,
        public AccountRole $role,
        public ?PhoneNumber $phone,
        public Locale $locale,
        public bool $emailVerified,
        public DateTimeImmutable $at,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
