<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Application\Command;
use App\SharedKernel\Application\CommandAuditor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Throwable;

/**
 * Outermost command-bus middleware: records every command's outcome through the {@see CommandAuditor} port,
 * outside the transaction, so a failure is audited even though the command's changes were rolled back.
 */
final readonly class AuditMiddleware implements MiddlewareInterface
{
    public function __construct(private CommandAuditor $auditor)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $command = $envelope->getMessage();
        if (!$command instanceof Command) {
            return $stack->next()->handle($envelope, $stack);
        }

        try {
            $result = $stack->next()->handle($envelope, $stack);
        } catch (Throwable $failure) {
            $this->auditor->recordFailure($command, $failure);

            throw $failure;
        }

        $this->auditor->recordSuccess($command);

        return $result;
    }
}
