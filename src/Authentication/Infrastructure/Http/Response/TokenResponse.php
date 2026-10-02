<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['accessToken', 'refreshToken', 'expiresIn', 'tokenType'])]
final readonly class TokenResponse
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $tokenType = 'Bearer',
    ) {
    }
}
