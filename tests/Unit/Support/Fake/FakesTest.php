<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Fake;

use App\SharedKernel\Domain\EntityId;
use App\Tests\Support\Fake\FrozenClock;
use App\Tests\Support\Fake\SequentialIdGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

final readonly class FakeId extends EntityId
{
}

#[CoversClass(FrozenClock::class)]
#[CoversClass(SequentialIdGenerator::class)]
final class FakesTest extends TestCase
{
    public function testFrozenClockDoesNotMoveUntilAdvanced(): void
    {
        $clock = new FrozenClock('2026-03-01T10:00:00+00:00');

        self::assertEquals($clock->now(), $clock->now());
        $clock->advance('+2 hours');

        self::assertSame('2026-03-01T12:00:00+00:00', $clock->now()->format('c'));
    }

    public function testFrozenClockIsAlwaysUtc(): void
    {
        $clock = new FrozenClock('2026-03-01T10:00:00+02:00');
        self::assertSame('UTC', $clock->now()->getTimezone()->getName());

        $clock->set(new DateTimeImmutable('2026-03-01T10:00:00+05:00'));
        self::assertSame('2026-03-01T05:00:00+00:00', $clock->now()->format('c'));
    }

    public function testSequentialIdsAreValidAndPredictable(): void
    {
        $generator = new SequentialIdGenerator();

        self::assertSame('01900000-0000-7000-8000-000000000001', $generator->generate());
        self::assertSame('01900000-0000-7000-8000-000000000002', $generator->generate());
        self::assertSame('01900000-0000-7000-8000-000000000003', new FakeId($generator->generate())->toString());
    }
}
