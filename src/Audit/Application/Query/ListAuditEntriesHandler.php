<?php

declare(strict_types=1);

namespace App\Audit\Application\Query;

use App\Audit\Contract\AuditTrail;
use App\Audit\Contract\Dto\AuditEntryPage;
use App\SharedKernel\Application\QueryHandler;

final readonly class ListAuditEntriesHandler implements QueryHandler
{
    public function __construct(private AuditTrail $trail)
    {
    }

    public function __invoke(ListAuditEntries $query): AuditEntryPage
    {
        return $this->trail->entries($query->actorId, $query->targetId, $query->name, $query->kind, $query->from, $query->to, $query->page, $query->perPage);
    }
}
