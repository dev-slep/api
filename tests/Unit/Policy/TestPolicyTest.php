<?php

declare(strict_types=1);

namespace App\Tests\Unit\Policy;

use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Application\QueryHandler;
use App\Tests\Support\Policy\ClassFinder;
use App\Tests\Support\Policy\Inventory;
use App\Tests\Support\Policy\TestPolicy;

use function dirname;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * `make test-policy`: every endpoint, console command, contract method and public method
 * must have the test required by spec §12.2.
 */
#[Group('policy')]
#[CoversNothing]
final class TestPolicyTest extends TestCase
{
    public function testEveryRequiredTestExists(): void
    {
        $root = dirname(__DIR__, 3);

        $policy = new TestPolicy(
            new Inventory(new ClassFinder($root.'/src', 'App\\'), [CommandHandler::class, QueryHandler::class]),
            new ClassFinder($root.'/tests/Unit', 'App\Tests\Unit\\'),
            new ClassFinder($root.'/tests/Application', 'App\Tests\Application\\'),
            new ClassFinder($root.'/tests/Integration', 'App\Tests\Integration\\'),
        );

        $report = $policy->check();

        self::assertTrue($report->isEmpty(), "Missing tests:\n".$report->format());
    }
}
