<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use App\Authentication\Domain\Model\Email;

interface TotpProvisioner
{
    /**
     * A new random base32 secret.
     */
    public function generateSecret(): string;

    /**
     * The `otpauth://` URI an authenticator app scans.
     */
    public function provisioningUri(string $secret, Email $account): string;
}
