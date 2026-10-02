<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

final readonly class IssuedAccessToken
{
    public function __construct(
        public string $token,
        public int $expiresInSeconds,
    ) {
    }
}
