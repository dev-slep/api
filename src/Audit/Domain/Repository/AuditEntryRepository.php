<?php

declare(strict_types=1);

namespace App\Audit\Domain\Repository;

use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Model\AuditEntryFilter;

/**
 * The audit log is append-only: there is no way to change or remove an entry.
 */
interface AuditEntryRepository
{
    public function add(AuditEntry $entry): void;

    /**
     * @return list<AuditEntry> newest first; $page starts at 1
     */
    public function search(AuditEntryFilter $filter, int $page, int $perPage): array;

    public function count(AuditEntryFilter $filter): int;
}
