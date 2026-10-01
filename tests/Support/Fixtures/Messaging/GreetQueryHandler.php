<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Messaging;

use App\SharedKernel\Application\QueryHandler;

final readonly class GreetQueryHandler implements QueryHandler
{
    public function __invoke(GreetQuery $query): string
    {
        return 'Hello '.$query->name;
    }
}
