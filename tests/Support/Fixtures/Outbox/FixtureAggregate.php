<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Outbox;

use App\SharedKernel\Domain\AggregateRoot;

final class FixtureAggregate extends AggregateRoot
{
    public function __construct(public readonly string $id)
    {
        $this->recordThat(new FixtureHappened($id));
    }
}
