<?php

declare(strict_types=1);

namespace App\Audit\Domain\Model;

enum ActorType: string
{
    case User = 'user';
    case Anonymous = 'anonymous';
    case System = 'system';
}
