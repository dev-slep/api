<?php

declare(strict_types=1);

namespace App\Penalty\Contract;

interface BlacklistChecker
{
    /**
     * True when the email address or the phone number belongs to a banned user and can't be used to register.
     * A null argument is not checked.
     */
    public function isBlacklisted(?string $email, ?string $phone): bool;
}
