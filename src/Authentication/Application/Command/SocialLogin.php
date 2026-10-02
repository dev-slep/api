<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\SharedKernel\Application\Command;

final readonly class SocialLogin implements Command
{
    public function __construct(
        public string $provider,
        public string $idToken,
        public ?string $role,
        public ?string $phone,
        public string $locale,
        public RequestContext $context,
    ) {
    }
}
