<?php

declare(strict_types=1);

namespace App\Penalty\Contract\Event;

/**
 * A user was permanently banned: their sessions must end and they can't log in.
 */
final readonly class UserBannedV1 extends PenaltyEvent
{
    public function eventName(): string
    {
        return 'penalty.user_banned.v1';
    }
}
