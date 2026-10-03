<?php

declare(strict_types=1);

namespace App\Audit\Contract\Dto;

use DateTimeImmutable;

/**
 * One audit entry as other modules and the admin API see it.
 */
final readonly class AuditEntryView
{
    /**
     * @param array<string, mixed> $payload secrets are already masked
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $occurredAt,
        public string $kind,
        public string $name,
        public string $actorType,
        public ?string $actorId,
        public ?string $actorRole,
        public ?string $targetId,
        public array $payload,
        public string $outcome,
        public ?string $failureReason,
        public ?string $correlationId,
        public ?string $ip,
        public ?string $userAgent,
    ) {
    }
}
