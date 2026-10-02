<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command\Result;

final readonly class RecoveryCodes
{
    /**
     * @param list<string> $codes single-use recovery codes, shown to the admin exactly once
     */
    public function __construct(public array $codes)
    {
    }
}
