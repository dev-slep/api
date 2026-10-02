<?php

declare(strict_types=1);

namespace App\Authentication\Application\Service;

use App\Authentication\Contract\Event\EmailVerifiedV1;
use App\Authentication\Contract\Event\PasswordChangedV1;
use App\Authentication\Contract\Event\TwoFactorEnabledV1;
use App\Authentication\Contract\Event\UserRegisteredV1;
use App\Authentication\Domain\Event\EmailVerified;
use App\Authentication\Domain\Event\PasswordChanged;
use App\Authentication\Domain\Event\TwoFactorEnabled;
use App\Authentication\Domain\Event\UserRegistered;
use App\SharedKernel\Application\DomainEventMapper;
use App\SharedKernel\Contract\UserId;
use App\SharedKernel\Domain\DomainEvent;
use App\SharedKernel\Domain\IdGenerator;

/**
 * Turns the account aggregates' domain events into the module's public integration events.
 */
final readonly class AuthenticationEventMapper implements DomainEventMapper
{
    public function __construct(private IdGenerator $ids)
    {
    }

    public function supports(DomainEvent $event): bool
    {
        return $event instanceof UserRegistered
            || $event instanceof EmailVerified
            || $event instanceof PasswordChanged
            || $event instanceof TwoFactorEnabled;
    }

    public function map(DomainEvent $event): array
    {
        $id = $this->ids->generate();

        return match (true) {
            $event instanceof UserRegistered => [new UserRegisteredV1(
                $id,
                $event->occurredAt(),
                new UserId($event->accountId->toString()),
                $event->email->toString(),
                $event->role->value,
                $event->phone?->toString(),
                $event->locale->toString(),
                $event->emailVerified,
            )],
            $event instanceof EmailVerified => [new EmailVerifiedV1($id, $event->occurredAt(), new UserId($event->accountId->toString()))],
            $event instanceof PasswordChanged => [new PasswordChangedV1($id, $event->occurredAt(), new UserId($event->accountId->toString()))],
            $event instanceof TwoFactorEnabled => [new TwoFactorEnabledV1($id, $event->occurredAt(), new UserId($event->accountId->toString()))],
            default => [],
        };
    }
}
