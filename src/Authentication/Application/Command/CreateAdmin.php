<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\SharedKernel\Application\Command;

final readonly class CreateAdmin implements Command
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
