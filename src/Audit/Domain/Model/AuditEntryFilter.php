<?php

declare(strict_types=1);

namespace App\Audit\Domain\Model;

use DateTimeImmutable;

/**
 * What an admin searches the audit log for; every criterion is optional and they are combined with AND.
 */
final readonly class AuditEntryFilter
{
    public function __construct(
        public ?string $actorId = null,
        public ?string $targetId = null,
        public ?string $name = null,
        public ?AuditKind $kind = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {
    }

    public function matches(AuditEntry $entry): bool
    {
        return (null === $this->actorId || $entry->actor->userId === $this->actorId)
            && (null === $this->targetId || $entry->targetId === $this->targetId)
            && (null === $this->name || $entry->name === $this->name)
            && (null === $this->kind || $entry->kind === $this->kind)
            && (null === $this->from || $entry->occurredAt >= $this->from)
            && (null === $this->to || $entry->occurredAt <= $this->to);
    }
}
