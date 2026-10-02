<?php

declare(strict_types=1);

namespace App\Penalty\Contract\Event;

/**
 * A user was suspended temporarily: their sessions must end.
 */
final readonly class UserSuspendedV1 extends PenaltyEvent
{
    public function eventName(): string
    {
        return 'penalty.user_suspended.v1';
    }
}
