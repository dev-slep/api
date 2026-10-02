<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\OpaqueToken;

interface AuthEmailSender
{
    /**
     * @throws EmailDeliveryFailed
     */
    public function sendEmailVerification(Email $to, Locale $locale, OpaqueToken $token): void;

    /**
     * @throws EmailDeliveryFailed
     */
    public function sendPasswordReset(Email $to, Locale $locale, OpaqueToken $token): void;
}
