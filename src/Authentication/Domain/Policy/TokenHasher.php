<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Policy;

use App\Authentication\Domain\Model\TokenHash;

interface TokenHasher
{
    /**
     * The hash that is stored instead of the raw token (SHA-256).
     */
    public function hash(string $rawToken): TokenHash;
}
