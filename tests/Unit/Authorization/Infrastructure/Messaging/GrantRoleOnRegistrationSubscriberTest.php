<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authorization\Infrastructure\Messaging;

use App\Authentication\Contract\Event\UserRegisteredV1;
use App\Authorization\Application\Command\GrantRole;
use App\Authorization\Infrastructure\Messaging\GrantRoleOnRegistrationSubscriber;
use App\SharedKernel\Application\IdempotentHandling;
use App\SharedKernel\Application\ProcessedEvents;
use App\SharedKernel\Contract\UserId;
use App\Tests\Support\Authentication\RecordingCommandBus;
use App\Tests\Support\Fake\NullTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrantRoleOnRegistrationSubscriber::class)]
final class GrantRoleOnRegistrationSubscriberTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';

    private RecordingCommandBus $bus;

    private function subscriber(): GrantRoleOnRegistrationSubscriber
    {
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

        return new GrantRoleOnRegistrationSubscriber($this->bus = new RecordingCommandBus(), new IdempotentHandling($processed, new NullTransaction()));
    }

    private function registered(string $eventId, string $role): UserRegisteredV1
    {
        return new UserRegisteredV1($eventId, new DateTimeImmutable(), new UserId(self::USER), 'ana@example.com', $role, null, 'sr_Latn', true);
    }

    public function testARegistrationGrantsTheRegisteredRole(): void
    {
        ($this->subscriber())($this->registered('e1', 'TOWER'));

        self::assertEquals([new GrantRole(self::USER, 'TOWER')], $this->bus->dispatched);
    }

    public function testARedeliveredEventGrantsOnce(): void
    {
        $subscriber = $this->subscriber();
        $event = $this->registered('e1', 'DRIVER');

        $subscriber($event);
        $subscriber($event);

        self::assertCount(1, $this->bus->dispatched);
    }
}
