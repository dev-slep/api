<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['recoveryCodes'])]
final readonly class RecoveryCodesResponse
{
    public function __construct(
        /** @var list<string> */
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
        public array $recoveryCodes,
    ) {
    }
}
