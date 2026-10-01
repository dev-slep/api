<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Application\Transaction;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Runs the rest of the command-bus chain (handler, outbox) in one transaction through the {@see Transaction} port.
 * Application tests swap the port for a transaction-less fake, so no database is needed.
 */
final readonly class TransactionMiddleware implements MiddlewareInterface
{
    public function __construct(private Transaction $transaction)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $this->transaction->run(static fn (): Envelope => $stack->next()->handle($envelope, $stack));
    }
}
