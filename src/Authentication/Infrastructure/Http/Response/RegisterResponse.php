<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['email', 'emailVerificationRequired'])]
final readonly class RegisterResponse
{
    public function __construct(
        public string $email,
        public bool $emailVerificationRequired,
    ) {
    }
}
