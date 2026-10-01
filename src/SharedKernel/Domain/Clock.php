<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

use DateTimeImmutable;

interface Clock
{
    /**
     * @return DateTimeImmutable the current time, always in UTC
     */
    public function now(): DateTimeImmutable;
}
