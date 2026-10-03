<?php

declare(strict_types=1);

namespace App\Audit\Application\Service;

use App\Audit\Domain\Model\Actor;
use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Model\AuditEntryId;
use App\Audit\Domain\Model\AuditKind;
use App\Audit\Domain\Model\Outcome;
use App\Audit\Domain\Policy\PayloadMasker;
use App\Audit\Domain\Repository\AuditEntryRepository;
use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;

use function is_string;

use ReflectionClass;

/**
 * Builds an audit entry from a command or an integration event (extracting and masking its payload) and stores it.
 */
final readonly class AuditRecorder
{
    private const int MAX_REASON_LENGTH = 500;

    public function __construct(
        private AuditEntryRepository $entries,
        private PayloadExtractor $extractor,
        private PayloadMasker $masker,
        private Clock $clock,
        private IdGenerator $ids,
    ) {
    }

    public function recordCommand(
        object $command,
        Actor $actor,
        ?string $failureReason,
        ?string $correlationId,
        ?string $ip,
        ?string $userAgent,
    ): void {
        $payload = $this->extractor->extract($command);
        $this->entries->add(new AuditEntry(
            new AuditEntryId($this->ids->generate()),
            $this->clock->now(),
            AuditKind::Command,
            (new ReflectionClass($command))->getShortName(),
            $actor,
            $this->extractor->targetId($payload),
            $this->masker->mask($payload),
            null === $failureReason ? Outcome::Success : Outcome::Failure,
            null === $failureReason ? null : mb_substr($failureReason, 0, self::MAX_REASON_LENGTH),
            $correlationId,
            $ip,
            $userAgent,
        ));
    }

    /**
     * Events carry no request of their own, so the actor is the account named by `accountId` (the Authentication
     * events), otherwise the system.
     */
    public function recordEvent(IntegrationEvent $event, ?string $correlationId): void
    {
        $payload = $this->extractor->extract($event);
        $actorId = $payload['accountId'] ?? null;
        $this->entries->add(new AuditEntry(
            new AuditEntryId($this->ids->generate()),
            $event->occurredAt(),
            AuditKind::Event,
            $event->eventName(),
            is_string($actorId) ? Actor::user($actorId, null) : Actor::system(),
            $this->extractor->targetId($payload),
            $this->masker->mask($payload),
            Outcome::Recorded,
            null,
            $correlationId,
            is_string($payload['ip'] ?? null) ? $payload['ip'] : null,
            is_string($payload['userAgent'] ?? null) ? $payload['userAgent'] : null,
        ));
    }
}
