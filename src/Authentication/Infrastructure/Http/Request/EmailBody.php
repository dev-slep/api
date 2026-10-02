<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['email'])]
final readonly class EmailBody
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 254)]
        public string $email = '',
    ) {
    }
}
