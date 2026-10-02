<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['code'])]
final readonly class CodeBody
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 32)]
        public string $code = '',
    ) {
    }
}
