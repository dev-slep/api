<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

enum AccountStatus: string
{
    case Active = 'ACTIVE';
    case Banned = 'BANNED';
}
