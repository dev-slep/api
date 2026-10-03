<?php

declare(strict_types=1);

namespace App\Authorization\Infrastructure\Messaging;

use App\Authentication\Contract\Event\UserRegisteredV1;
use App\Authorization\Application\Command\GrantRole;
use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Application\IdempotentHandling;

/**
 * Gives every newly registered account (admins created by the console command included) the role it registered with.
 * A redelivered event changes nothing.
 */
final readonly class GrantRoleOnRegistrationSubscriber
{
    public const string NAME = 'authorization.grant_role_on_registration';

    public function __construct(
        private CommandBus $commandBus,
        private IdempotentHandling $idempotent,
    ) {
    }

    public function __invoke(UserRegisteredV1 $event): void
    {
        $this->idempotent->handle(self::NAME, $event, function () use ($event): void {
            $this->commandBus->dispatch(new GrantRole($event->accountId->toString(), $event->role));
        });
    }
}
