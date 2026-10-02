<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Policy;

use RuntimeException;

interface SecretEncrypter
{
    /**
     * Encrypts a secret for storage (authenticated encryption).
     */
    public function encrypt(string $plain): string;

    /**
     * @throws RuntimeException when the value was tampered with or encrypted with another key
     */
    public function decrypt(string $encrypted): string;
}
