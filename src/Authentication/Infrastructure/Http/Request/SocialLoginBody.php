<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['idToken'])]
final readonly class SocialLoginBody
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 8192)]
        public string $idToken = '',
        #[Assert\Choice(choices: ['DRIVER', 'TOWER'])]
        public ?string $role = null,
        #[Assert\Length(max: 32)]
        public ?string $phone = null,
        #[Assert\Regex(pattern: '/^[a-z]{2}(_[A-Za-z]{2,4})?$/')]
        public ?string $locale = null,
    ) {
    }
}
