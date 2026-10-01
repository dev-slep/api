<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Scheduler;

use App\SharedKernel\Infrastructure\Scheduler\DefaultSchedule;
use App\SharedKernel\Infrastructure\Scheduler\ScheduledTaskProvider;
use App\Tests\Support\Fixtures\Messaging\RecordingCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Contracts\Cache\CacheInterface;

#[CoversClass(DefaultSchedule::class)]
final class DefaultScheduleTest extends TestCase
{
    public function testScheduleWithoutProvidersIsEmpty(): void
    {
        $schedule = $this->defaultSchedule([])->getSchedule();

        self::assertSame([], $schedule->getRecurringMessages());
    }

    public function testScheduleContainsTheMessagesOfEveryProvider(): void
    {
        $first = RecurringMessage::every('1 hour', new RecordingCommand('first'));
        $second = RecurringMessage::every('1 day', new RecordingCommand('second'));
        $third = RecurringMessage::every('1 week', new RecordingCommand('third'));

        $schedule = $this->defaultSchedule([$this->provider([$first, $second]), $this->provider([$third])])->getSchedule();

        self::assertSame([$first, $second, $third], array_values($schedule->getRecurringMessages()));
    }

    public function testProviderWithoutMessagesContributesNothing(): void
    {
        $only = RecurringMessage::every('1 hour', new RecordingCommand('only'));

        $schedule = $this->defaultSchedule([$this->provider([]), $this->provider([$only])])->getSchedule();

        self::assertSame([$only], array_values($schedule->getRecurringMessages()));
    }

    public function testScheduleIsGuardedByAPostgresLockNamedAfterTheTransport(): void
    {
        $lockFactory = self::createMock(LockFactory::class);
        $lockFactory->expects(self::once())->method('createLock')->with('scheduler_default')->willReturn(self::createStub(SharedLockInterface::class));

        (new DefaultSchedule(self::createStub(CacheInterface::class), $lockFactory, []))->getSchedule();
    }

    /**
     * @param list<ScheduledTaskProvider> $providers
     */
    private function defaultSchedule(array $providers): DefaultSchedule
    {
        $lockFactory = self::createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturn(self::createStub(SharedLockInterface::class));

        return new DefaultSchedule(self::createStub(CacheInterface::class), $lockFactory, $providers);
    }

    /**
     * @param list<RecurringMessage> $messages
     */
    private function provider(array $messages): ScheduledTaskProvider
    {
        $provider = self::createStub(ScheduledTaskProvider::class);
        $provider->method('recurringMessages')->willReturn($messages);

        return $provider;
    }
}
