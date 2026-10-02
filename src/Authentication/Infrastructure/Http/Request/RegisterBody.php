<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['email', 'password', 'role'])]
final readonly class RegisterBody
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 254)]
        #[Assert\Email]
        public string $email = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public string $password = '',
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['DRIVER', 'TOWER'])]
        public string $role = '',
        #[Assert\Length(max: 32)]
        public ?string $phone = null,
        #[Assert\Regex(pattern: '/^[a-z]{2}(_[A-Za-z]{2,4})?$/')]
        public ?string $locale = null,
    ) {
    }
}
