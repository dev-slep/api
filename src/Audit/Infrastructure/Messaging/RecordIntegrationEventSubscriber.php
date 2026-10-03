<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Messaging;

use App\Audit\Application\Service\AuditRecorder;
use App\SharedKernel\Application\IdempotentHandling;
use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Infrastructure\Correlation\CorrelationContext;

/**
 * Stores every integration event of every module in the audit log. A redelivered event is stored once.
 */
final readonly class RecordIntegrationEventSubscriber
{
    public const string NAME = 'audit.record_integration_event';

    public function __construct(
        private AuditRecorder $recorder,
        private IdempotentHandling $idempotent,
        private CorrelationContext $correlation,
    ) {
    }

    public function __invoke(IntegrationEvent $event): void
    {
        $this->idempotent->handle(self::NAME, $event, function () use ($event): void {
            $this->recorder->recordEvent($event, $this->correlation->correlationId());
        });
    }
}
