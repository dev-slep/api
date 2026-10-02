<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Crypto;

use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Policy\TokenHasher;

/**
 * Tokens carry 256 bits of randomness, so a plain SHA-256 is enough to store them (no salt or slow hash needed).
 */
final readonly class Sha256TokenHasher implements TokenHasher
{
    public function hash(string $rawToken): TokenHash
    {
        return new TokenHash(hash('sha256', $rawToken));
    }
}
