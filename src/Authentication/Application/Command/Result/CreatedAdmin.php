<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command\Result;

final readonly class CreatedAdmin
{
    public function __construct(
        public string $accountId,
        public TwoFactorEnrolment $enrolment,
    ) {
    }
}
