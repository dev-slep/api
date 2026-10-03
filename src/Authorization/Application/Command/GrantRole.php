<?php

declare(strict_types=1);

namespace App\Authorization\Application\Command;

use App\SharedKernel\Application\Command;

final readonly class GrantRole implements Command
{
    public function __construct(
        public string $userId,
        public string $role,
    ) {
    }
}
