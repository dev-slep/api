<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Http;

use App\SharedKernel\Domain\DomainException;
use App\SharedKernel\Domain\ProblemType;

final class FixtureProblemException extends DomainException implements ProblemType
{
    public function problemSlug(): string
    {
        return 'fixture-conflict';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
