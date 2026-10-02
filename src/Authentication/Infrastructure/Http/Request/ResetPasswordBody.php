<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['token', 'newPassword'])]
final readonly class ResetPasswordBody
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 256)]
        public string $token = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public string $newPassword = '',
    ) {
    }
}
