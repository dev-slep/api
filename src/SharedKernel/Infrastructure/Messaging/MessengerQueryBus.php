<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Application\Query;
use App\SharedKernel\Application\QueryBus;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerQueryBus implements QueryBus
{
    use UnwrapsHandlerFailure;

    public function __construct(#[Target('query.bus')] private MessageBusInterface $queryBus)
    {
    }

    public function ask(Query $query): mixed
    {
        try {
            $envelope = $this->queryBus->dispatch($query);
        } catch (HandlerFailedException $failure) {
            throw $this->unwrap($failure);
        }

        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
