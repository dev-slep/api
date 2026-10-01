<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Messaging;

use App\SharedKernel\Application\QueryHandler;

final readonly class FailingQueryHandler implements QueryHandler
{
    public function __invoke(FailingQuery $query): never
    {
        throw new FixtureDomainException('Query failed on purpose.');
    }
}
