<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Application\Command;
use App\SharedKernel\Application\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerCommandBus implements CommandBus
{
    use UnwrapsHandlerFailure;

    /** The default bus is the command bus (framework.messenger.default_bus). */
    public function __construct(private MessageBusInterface $commandBus)
    {
    }

    public function dispatch(Command $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $failure) {
            throw $this->unwrap($failure);
        }
    }
}
