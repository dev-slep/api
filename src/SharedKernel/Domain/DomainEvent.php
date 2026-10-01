<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
