<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

enum OneTimeTokenPurpose: string
{
    case EmailVerification = 'EMAIL_VERIFICATION';
    case PasswordReset = 'PASSWORD_RESET';
}
