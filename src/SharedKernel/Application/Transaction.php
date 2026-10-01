<?php

declare(strict_types=1);

namespace App\SharedKernel\Application;

interface Transaction
{
    /**
     * Runs the callable in one transaction: commits when it returns, rolls back when it throws.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(callable $work): mixed;
}
