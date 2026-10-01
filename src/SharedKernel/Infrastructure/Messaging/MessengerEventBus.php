<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Application\EventBus;
use App\SharedKernel\Contract\IntegrationEvent;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerEventBus implements EventBus
{
    public function __construct(#[Target('event.bus')] private MessageBusInterface $eventBus)
    {
    }

    public function publish(IntegrationEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
