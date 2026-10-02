<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\SharedKernel\Application\Command;

final readonly class ResetPassword implements Command
{
    public function __construct(
        public string $token,
        public string $newPassword,
    ) {
    }
}
