<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Correlation;

use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Domain\IdGenerator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Outgoing messages get a {@see CorrelationStamp}; messages received by the worker restore the context from it,
 * with the causation id set to the handled event's id.
 */
final readonly class CorrelationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CorrelationContext $context,
        private IdGenerator $ids,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(CorrelationStamp::class);

        if (null !== $envelope->last(ReceivedStamp::class)) {
            if (null !== $stamp) {
                $message = $envelope->getMessage();
                $this->context->start(
                    $stamp->correlationId,
                    $message instanceof IntegrationEvent ? $message->eventId() : $stamp->causationId,
                );
            }

            return $stack->next()->handle($envelope, $stack);
        }

        if (null === $stamp) {
            $correlationId = $this->context->correlationId();
            if (null === $correlationId) {
                $correlationId = $this->ids->generate();
                $this->context->start($correlationId);
            }

            $envelope = $envelope->with(new CorrelationStamp($correlationId, $this->context->causationId()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
