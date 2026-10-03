<?php

declare(strict_types=1);

namespace App\Audit\Domain\Model;

enum AuditKind: string
{
    case Command = 'command';
    case Event = 'event';
}
