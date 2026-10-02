<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\SocialIdentity;

final readonly class VerifiedSocialIdentity
{
    public function __construct(
        public SocialIdentity $identity,
        public Email $email,
        public bool $emailVerified,
        public bool $privateRelayEmail = false,
    ) {
    }
}
