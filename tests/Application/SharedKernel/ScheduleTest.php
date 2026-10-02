<?php

declare(strict_types=1);

namespace App\Tests\Application\SharedKernel;

use App\SharedKernel\Infrastructure\Scheduler\DefaultSchedule;
use App\Tests\Support\ApplicationTestCase;

use function count;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ScheduleTest extends ApplicationTestCase
{
    public function testTaggedProvidersAreCollectedIntoTheDefaultSchedule(): void
    {
        $messages = array_values(static::getContainer()->get(DefaultSchedule::class)->getSchedule()->getRecurringMessages());

        // The test fixture's task plus the modules' own tasks (e.g. the daily purge of expired authentication tokens)
        self::assertGreaterThanOrEqual(1, count($messages));
    }
}
