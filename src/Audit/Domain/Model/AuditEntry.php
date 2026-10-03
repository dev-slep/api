<?php

declare(strict_types=1);

namespace App\Audit\Domain\Model;

use DateTimeImmutable;

/**
 * One line of the audit log. Immutable and never changed after it was written.
 */
final readonly class AuditEntry
{
    /**
     * @param array<string, mixed> $payload already masked, see {@see \App\Audit\Domain\Policy\PayloadMasker}
     */
    public function __construct(
        public AuditEntryId $id,
        public DateTimeImmutable $occurredAt,
        public AuditKind $kind,
        public string $name,
        public Actor $actor,
        public ?string $targetId,
        public array $payload,
        public Outcome $outcome,
        public ?string $failureReason,
        public ?string $correlationId,
        public ?string $ip,
        public ?string $userAgent,
    ) {
    }
}
