<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Messaging;

use App\SharedKernel\Contract\IntegrationEvent;
use DateTimeImmutable;

final readonly class FixtureIntegrationEvent implements IntegrationEvent
{
    public function __construct(
        private string $id = '01900000-0000-7000-8000-0000000000aa',
        private DateTimeImmutable $occurredAt = new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
    ) {
    }

    public function eventId(): string
    {
        return $this->id;
    }

    public function eventName(): string
    {
        return 'fixture.happened.v1';
    }

    public function version(): int
    {
        return 1;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
