<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['secret', 'provisioningUri'])]
final readonly class EnrolmentResponse
{
    public function __construct(
        public string $secret,
        public string $provisioningUri,
    ) {
    }
}
