<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command\Result;

final readonly class TwoFactorEnrolment
{
    public function __construct(
        public string $secret,
        public string $provisioningUri,
    ) {
    }
}
