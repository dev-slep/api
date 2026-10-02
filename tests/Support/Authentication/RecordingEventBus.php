<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\SharedKernel\Application\EventBus;
use App\SharedKernel\Contract\IntegrationEvent;

final class RecordingEventBus implements EventBus
{
    /** @var list<IntegrationEvent> */
    public array $published = [];

    public function publish(IntegrationEvent ...$events): void
    {
        array_push($this->published, ...$events);
    }

    /**
     * @template T of IntegrationEvent
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function of(string $class): array
    {
        return array_values(array_filter($this->published, static fn (IntegrationEvent $event): bool => $event instanceof $class));
    }
}
