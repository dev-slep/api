<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Pattern for repository contract tests: the behaviour is written once, in an abstract
 * test class per repository interface that extends this class, and executed against both
 * the in-memory and the Doctrine implementation by two thin concrete subclasses.
 * See tests/README.md.
 *
 * @template TRepository of object
 */
abstract class RepositoryContractTestCase extends TestCase
{
    /**
     * @return TRepository
     */
    abstract protected function createRepository(): object;
}
