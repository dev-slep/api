<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\SharedKernel\Application\Transaction;

/**
 * Runs the work without a database: application tests never touch Postgres.
 */
final class NullTransaction implements Transaction
{
    public int $runs = 0;

    public function run(callable $work): mixed
    {
        ++$this->runs;

        return $work();
    }
}
