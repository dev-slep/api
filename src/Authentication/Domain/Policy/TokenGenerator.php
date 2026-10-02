<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Policy;

use App\Authentication\Domain\Model\OpaqueToken;

interface TokenGenerator
{
    /**
     * A new unguessable token (at least 256 bits of randomness).
     */
    public function generate(): OpaqueToken;

    /**
     * A new two-factor recovery code in the form "xxxx-xxxx-xxxx" (lowercase alphanumeric, easy to type).
     */
    public function recoveryCode(): string;
}
