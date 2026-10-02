<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Messaging;

use App\Authentication\Application\Command\BanAccount;
use App\Authentication\Application\Command\PurgeExpiredTokens;
use App\Authentication\Application\Command\RevokeAllRefreshTokens;
use App\Authentication\Infrastructure\Messaging\RevokeSessionsOnPenaltySubscriber;
use App\Authentication\Infrastructure\Scheduler\PurgeExpiredTokensTask;
use App\Penalty\Contract\Event\UserBannedV1;
use App\Penalty\Contract\Event\UserSuspendedV1;
use App\SharedKernel\Application\IdempotentHandling;
use App\SharedKernel\Application\ProcessedEvents;
use App\SharedKernel\Contract\UserId;
use App\Tests\Support\Authentication\RecordingCommandBus;
use App\Tests\Support\Fake\NullTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

#[CoversClass(RevokeSessionsOnPenaltySubscriber::class)]
#[CoversClass(PurgeExpiredTokensTask::class)]
final class PenaltySubscriberAndSchedulerTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';

    private RecordingCommandBus $bus;

    private function subscriber(): RevokeSessionsOnPenaltySubscriber
    {
        $bus = $this->bus = new RecordingCommandBus();
        $processed = new class implements ProcessedEvents {
            /** @var array<string, true> */
            private array $seen = [];

            public function wasProcessed(string $subscriber, string $eventId): bool
            {
                return isset($this->seen[$subscriber.$eventId]);
            }

            public function markProcessed(string $subscriber, string $eventId): void
            {
                $this->seen[$subscriber.$eventId] = true;
            }
        };

        return new RevokeSessionsOnPenaltySubscriber($bus, new IdempotentHandling($processed, new NullTransaction()));
    }

    public function testABanBansTheAccountAndEndsItsSessions(): void
    {
        ($this->subscriber())(new UserBannedV1('e1', new DateTimeImmutable(), new UserId(self::USER), 'no-shows'));

        self::assertEquals([new BanAccount(self::USER)], $this->bus->dispatched);
    }

    public function testASuspensionOnlyEndsTheSessions(): void
    {
        ($this->subscriber())(new UserSuspendedV1('e1', new DateTimeImmutable(), new UserId(self::USER), 'no-shows'));

        self::assertEquals([new RevokeAllRefreshTokens(self::USER)], $this->bus->dispatched);
    }

    public function testARedeliveredEventChangesNothing(): void
    {
        $subscriber = $this->subscriber();
        $event = new UserBannedV1('e1', new DateTimeImmutable(), new UserId(self::USER), 'no-shows');

        $subscriber($event);
        $subscriber($event);

        self::assertCount(1, $this->bus->dispatched);
    }

    public function testDifferentEventsAreAllHandled(): void
    {
        $subscriber = $this->subscriber();

        $subscriber(new UserBannedV1('e1', new DateTimeImmutable(), new UserId(self::USER), 'x'));
        $subscriber(new UserBannedV1('e2', new DateTimeImmutable(), new UserId(self::USER), 'x'));

        self::assertCount(2, $this->bus->dispatched);
    }

    public function testThePurgeIsScheduledDaily(): void
    {
        $messages = (new PurgeExpiredTokensTask())->recurringMessages();

        self::assertCount(1, $messages);
        $trigger = $messages[0]->getTrigger();
        self::assertInstanceOf(PeriodicalTrigger::class, $trigger);
        $from = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        self::assertEquals($from->modify('+1 day'), $trigger->getNextRunDate($from));
        self::assertEquals([new PurgeExpiredTokens()], [...$messages[0]->getProvider()->getMessages(new MessageContext('default', 'purge', $trigger, $from))]);
    }
}
