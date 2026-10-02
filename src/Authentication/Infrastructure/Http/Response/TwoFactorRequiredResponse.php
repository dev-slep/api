<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['accessToken', 'expiresIn', 'twoFactorRequired', 'tokenType'])]
final readonly class TwoFactorRequiredResponse
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public bool $twoFactorRequired,
        public string $tokenType = 'Bearer',
    ) {
    }
}
