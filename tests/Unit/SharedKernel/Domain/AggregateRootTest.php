<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Domain;

use App\SharedKernel\Domain\AggregateRoot;
use App\SharedKernel\Domain\DomainEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

final class RecordingAggregate extends AggregateRoot
{
    public function record(DomainEvent $event): void
    {
        $this->recordThat($event);
    }
}

#[CoversClass(AggregateRoot::class)]
final class AggregateRootTest extends TestCase
{
    public function testNoEventsAreReleasedFromAFreshAggregate(): void
    {
        self::assertSame([], $this->aggregate()->releaseEvents());
    }

    public function testRecordedEventsAreReleasedInOrder(): void
    {
        $first = $this->event();
        $second = $this->event();
        $aggregate = $this->aggregate();

        $aggregate->record($first);
        $aggregate->record($second);

        self::assertSame([$first, $second], $aggregate->releaseEvents());
    }

    public function testReleasingClearsTheEvents(): void
    {
        $aggregate = $this->aggregate();
        $aggregate->record($this->event());

        $aggregate->releaseEvents();

        self::assertSame([], $aggregate->releaseEvents());
    }

    public function testEventsRecordedAfterReleaseAreReleasedAgain(): void
    {
        $aggregate = $this->aggregate();
        $aggregate->record($this->event());
        $aggregate->releaseEvents();
        $later = $this->event();

        $aggregate->record($later);

        self::assertSame([$later], $aggregate->releaseEvents());
    }

    private function aggregate(): RecordingAggregate
    {
        return new RecordingAggregate();
    }

    private function event(): DomainEvent
    {
        return new class implements DomainEvent {
            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-01-01T00:00:00+00:00');
            }
        };
    }
}
