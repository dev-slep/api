<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence;

use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Model\AuditEntryFilter;
use App\Audit\Domain\Repository\AuditEntryRepository;

use function array_slice;
use function count;

/**
 * Used by application tests, which never touch the database.
 */
final class InMemoryAuditEntryRepository implements AuditEntryRepository
{
    /** @var list<AuditEntry> */
    private array $entries = [];

    public function add(AuditEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function search(AuditEntryFilter $filter, int $page, int $perPage): array
    {
        $matching = array_values(array_filter($this->entries, $filter->matches(...)));
        usort($matching, static fn (AuditEntry $a, AuditEntry $b): int => [$b->occurredAt, $b->id->toString()] <=> [$a->occurredAt, $a->id->toString()]);

        return array_slice($matching, ($page - 1) * $perPage, $perPage);
    }

    public function count(AuditEntryFilter $filter): int
    {
        return count(array_filter($this->entries, $filter->matches(...)));
    }

    /**
     * @return list<AuditEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
