<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\SharedKernel\Domain\IdGenerator;

use function sprintf;

/**
 * Predictable, UUIDv7-shaped identifiers: 01900000-0000-7000-8000-000000000001, …0002, ….
 */
final class SequentialIdGenerator implements IdGenerator
{
    private int $counter = 0;

    public function generate(): string
    {
        ++$this->counter;

        return sprintf('01900000-0000-7000-8000-%012d', $this->counter);
    }
}
