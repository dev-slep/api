<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Scheduler;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The one schedule of the application (transport `scheduler_default`), assembled from every tagged
 * {@see ScheduledTaskProvider}. A Postgres lock makes sure only one worker triggers each run, and the
 * stateful cache makes missed runs predictable (only the last missed run is processed).
 */
#[AsSchedule('default')]
final readonly class DefaultSchedule implements ScheduleProviderInterface
{
    /**
     * @param iterable<ScheduledTaskProvider> $providers
     */
    public function __construct(
        private CacheInterface $cache,
        private LockFactory $lockFactory,
        #[AutowireIterator('app.scheduled_task')]
        private iterable $providers,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = (new Schedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->lockFactory->createLock('scheduler_default'));

        foreach ($this->providers as $provider) {
            foreach ($provider->recurringMessages() as $message) {
                $schedule->add($message);
            }
        }

        return $schedule;
    }
}
