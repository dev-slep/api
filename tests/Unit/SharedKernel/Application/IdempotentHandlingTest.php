<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Application;

use App\SharedKernel\Application\IdempotentHandling;
use App\SharedKernel\Application\ProcessedEvents;
use App\Tests\Support\Fake\NullTransaction;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(IdempotentHandling::class)]
final class IdempotentHandlingTest extends TestCase
{
    public function testWorkRunsOnceAndTheEventIsMarkedProcessed(): void
    {
        $processed = new InMemoryProcessedEvents();
        $handling = new IdempotentHandling($processed, new NullTransaction());
        $runs = 0;
        $event = new FixtureIntegrationEvent();

        $first = $handling->handle('billing', $event, static function () use (&$runs): void {
            ++$runs;
        });
        $second = $handling->handle('billing', $event, static function () use (&$runs): void {
            ++$runs;
        });

        self::assertTrue($first);
        self::assertFalse($second);
        self::assertSame(1, $runs);
    }

    public function testDifferentSubscribersProcessTheSameEventIndependently(): void
    {
        $handling = new IdempotentHandling(new InMemoryProcessedEvents(), new NullTransaction());
        $runs = 0;
        $event = new FixtureIntegrationEvent();
        $work = static function () use (&$runs): void {
            ++$runs;
        };

        $handling->handle('billing', $event, $work);
        $handling->handle('notification', $event, $work);

        self::assertSame(2, $runs);
    }

    public function testFailedWorkIsNotMarkedAsProcessed(): void
    {
        $processed = new InMemoryProcessedEvents();
        $handling = new IdempotentHandling($processed, new NullTransaction());
        $event = new FixtureIntegrationEvent();

        try {
            $handling->handle('billing', $event, static function (): never {
                throw new RuntimeException('boom');
            });
            self::fail('Expected the work failure to propagate');
        } catch (RuntimeException) {
        }

        self::assertFalse($processed->wasProcessed('billing', $event->eventId()));
    }

    public function testWorkRunsInsideTheTransaction(): void
    {
        $transaction = new NullTransaction();

        (new IdempotentHandling(new InMemoryProcessedEvents(), $transaction))->handle('billing', new FixtureIntegrationEvent(), static function (): void {
        });

        self::assertSame(1, $transaction->runs);
    }
}

final class InMemoryProcessedEvents implements ProcessedEvents
{
    /** @var array<string, true> */
    private array $processed = [];

    public function wasProcessed(string $subscriber, string $eventId): bool
    {
        return isset($this->processed[$subscriber.'|'.$eventId]);
    }

    public function markProcessed(string $subscriber, string $eventId): void
    {
        $this->processed[$subscriber.'|'.$eventId] = true;
    }
}
