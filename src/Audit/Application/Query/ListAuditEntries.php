<?php

declare(strict_types=1);

namespace App\Audit\Application\Query;

use App\SharedKernel\Application\Query;
use DateTimeImmutable;

final readonly class ListAuditEntries implements Query
{
    public function __construct(
        public ?string $actorId = null,
        public ?string $targetId = null,
        public ?string $name = null,
        public ?string $kind = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {
    }
}
