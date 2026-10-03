<?php

declare(strict_types=1);

namespace App\Audit\Contract;

use App\Audit\Contract\Dto\AuditEntryPage;
use DateTimeImmutable;

/**
 * Read access to the audit log, for admin features.
 */
interface AuditTrail
{
    /**
     * @param string|null $kind "command" or "event"
     * @param int         $page starts at 1
     */
    public function entries(
        ?string $actorId = null,
        ?string $targetId = null,
        ?string $name = null,
        ?string $kind = null,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        int $page = 1,
        int $perPage = 50,
    ): AuditEntryPage;
}
