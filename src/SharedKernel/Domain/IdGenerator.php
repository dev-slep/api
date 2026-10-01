<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

interface IdGenerator
{
    /**
     * @return string a new UUIDv7
     */
    public function generate(): string;
}
