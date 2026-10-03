<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Messaging;

use App\Audit\Application\Service\AuditRecorder;
use App\Audit\Domain\Model\Actor;
use App\Authentication\Contract\CurrentUser;
use App\SharedKernel\Application\Command;
use App\SharedKernel\Application\CommandAuditor;
use App\SharedKernel\Infrastructure\Correlation\CorrelationContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

/**
 * Writes an audit entry for every command, whether it succeeded or failed. Auditing never breaks the business
 * operation: if the entry cannot be written, the problem is logged and the command's own outcome stands.
 */
final readonly class AuditCommandAuditor implements CommandAuditor
{
    private const int MAX_USER_AGENT_LENGTH = 255;

    public function __construct(
        private AuditRecorder $recorder,
        private CurrentUser $currentUser,
        private RequestStack $requests,
        private CorrelationContext $correlation,
        private LoggerInterface $logger,
    ) {
    }

    public function recordSuccess(Command $command): void
    {
        $this->record($command, null);
    }

    public function recordFailure(Command $command, Throwable $failure): void
    {
        $this->record($command, $failure::class.': '.$failure->getMessage());
    }

    private function record(Command $command, ?string $failureReason): void
    {
        try {
            $request = $this->requests->getMainRequest();
            $userAgent = $request?->headers->get('User-Agent');

            $this->recorder->recordCommand(
                $command,
                $this->actor(null !== $request),
                $failureReason,
                $this->correlation->correlationId(),
                $request?->getClientIp(),
                null === $userAgent ? null : mb_substr($userAgent, 0, self::MAX_USER_AGENT_LENGTH),
            );
        } catch (Throwable $problem) {
            $this->logger->error('The audit entry of a command could not be written.', ['command' => $command::class, 'exception' => $problem]);
        }
    }

    private function actor(bool $viaHttp): Actor
    {
        if ($this->currentUser->isAuthenticated()) {
            return Actor::user($this->currentUser->id()->toString(), $this->currentUser->roles()[0] ?? null);
        }

        return $viaHttp ? Actor::anonymous() : Actor::system();
    }
}
