<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Messaging;

use App\Authentication\Application\Command\BanAccount;
use App\Authentication\Application\Command\RevokeAllRefreshTokens;
use App\Penalty\Contract\Event\PenaltyEvent;
use App\Penalty\Contract\Event\UserBannedV1;
use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Application\IdempotentHandling;

/**
 * Ends a user's sessions when Penalty bans or suspends them. A redelivered event changes nothing.
 * Access tokens that were already issued stay valid until they expire (15 minutes).
 */
final readonly class RevokeSessionsOnPenaltySubscriber
{
    public const string NAME = 'authentication.revoke_sessions_on_penalty';

    public function __construct(
        private CommandBus $commandBus,
        private IdempotentHandling $idempotent,
    ) {
    }

    public function __invoke(PenaltyEvent $event): void
    {
        $this->idempotent->handle(self::NAME, $event, function () use ($event): void {
            $this->commandBus->dispatch(
                $event instanceof UserBannedV1
                    ? new BanAccount($event->userId->toString())
                    : new RevokeAllRefreshTokens($event->userId->toString()),
            );
        });
    }
}
