<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

enum SocialProvider: string
{
    case Google = 'google';
    case Apple = 'apple';
}
