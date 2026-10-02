<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use OTPHP\TOTP;

/**
 * Computes the code an authenticator app would show, for tests.
 */
final class TotpCodes
{
    /**
     * @param non-empty-string $secret base32 secret
     */
    public static function at(string $secret, int $timestamp): string
    {
        return TOTP::createFromSecret($secret, null)->at(max(0, $timestamp));
    }
}
