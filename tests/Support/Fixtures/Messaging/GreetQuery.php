<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Messaging;

use App\SharedKernel\Application\Query;

final readonly class GreetQuery implements Query
{
    public function __construct(public string $name)
    {
    }
}
