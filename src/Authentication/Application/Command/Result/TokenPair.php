<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command\Result;

final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresInSeconds,
    ) {
    }
}
