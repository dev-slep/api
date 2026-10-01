<?php

declare(strict_types=1);

namespace App\Tests\Application\SharedKernel;

use App\SharedKernel\Infrastructure\Scheduler\DefaultSchedule;
use App\Tests\Support\ApplicationTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ScheduleTest extends ApplicationTestCase
{
    public function testTaggedProvidersAreCollectedIntoTheDefaultSchedule(): void
    {
        $messages = array_values(static::getContainer()->get(DefaultSchedule::class)->getSchedule()->getRecurringMessages());

        self::assertCount(1, $messages);
    }
}
