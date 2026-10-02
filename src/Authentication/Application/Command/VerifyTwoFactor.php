<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\SharedKernel\Application\Command;

final readonly class VerifyTwoFactor implements Command
{
    public function __construct(
        public string $accountId,
        public string $code,
        public RequestContext $context,
    ) {
    }
}
