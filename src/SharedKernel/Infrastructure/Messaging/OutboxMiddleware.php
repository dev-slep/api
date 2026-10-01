<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Application\Command;
use App\SharedKernel\Application\DomainEventMapper;
use App\SharedKernel\Application\EventBus;
use App\SharedKernel\Contract\IntegrationEvent;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Transactional outbox: sits inside the transaction middleware. After the handler succeeds it releases the
 * domain events of the saved aggregates, maps them to integration events and publishes them before the commit.
 * The Doctrine transport uses the same connection, so state and events commit or roll back together.
 */
final readonly class OutboxMiddleware implements MiddlewareInterface
{
    /**
     * @param iterable<DomainEventMapper> $mappers
     */
    public function __construct(
        private CollectedAggregateEvents $collector,
        private EventBus $eventBus,
        #[AutowireIterator('app.domain_event_mapper')]
        private iterable $mappers,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->getMessage() instanceof Command) {
            return $stack->next()->handle($envelope, $stack);
        }

        try {
            $result = $stack->next()->handle($envelope, $stack);

            $this->eventBus->publish(...$this->integrationEvents());
        } finally {
            $this->collector->reset();
        }

        return $result;
    }

    /**
     * @return list<IntegrationEvent>
     */
    private function integrationEvents(): array
    {
        $integrationEvents = [];
        foreach ($this->collector->releaseEvents() as $domainEvent) {
            foreach ($this->mappers as $mapper) {
                if ($mapper->supports($domainEvent)) {
                    array_push($integrationEvents, ...$mapper->map($domainEvent));
                }
            }
        }

        return $integrationEvents;
    }
}
