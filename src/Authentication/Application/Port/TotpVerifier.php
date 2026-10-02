<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use DateTimeImmutable;

interface TotpVerifier
{
    /**
     * Checks a code against the secret, allowing one time step of clock drift either way.
     *
     * @return int|null the time step the code belongs to, null when the code is wrong
     */
    public function verify(string $secret, string $code, DateTimeImmutable $now): ?int;
}
