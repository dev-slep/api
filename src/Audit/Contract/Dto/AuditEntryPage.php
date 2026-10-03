<?php

declare(strict_types=1);

namespace App\Audit\Contract\Dto;

final readonly class AuditEntryPage
{
    /**
     * @param list<AuditEntryView> $items newest first
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {
    }
}
