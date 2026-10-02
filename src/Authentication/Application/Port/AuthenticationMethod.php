<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

/**
 * How the holder of an access token proved who they are (the `amr` claim).
 */
enum AuthenticationMethod: string
{
    case Password = 'pwd';
    case Social = 'social';
    case PasswordAndOtp = 'pwd+otp';
    /** Admin passed the password step; only the two-factor endpoints accept this token. */
    case PendingTwoFactor = 'pending_2fa';
}
