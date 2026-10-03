<?php

declare(strict_types=1);

namespace App\Audit\Application\ContractImplementation;

use App\Audit\Contract\AuditTrail;
use App\Audit\Contract\Dto\AuditEntryPage;
use App\Audit\Contract\Dto\AuditEntryView;
use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Model\AuditEntryFilter;
use App\Audit\Domain\Model\AuditKind;
use App\Audit\Domain\Repository\AuditEntryRepository;
use DateTimeImmutable;

final readonly class AuditTrailFacade implements AuditTrail
{
    private const int MAX_PER_PAGE = 100;

    public function __construct(private AuditEntryRepository $entries)
    {
    }

    public function entries(
        ?string $actorId = null,
        ?string $targetId = null,
        ?string $name = null,
        ?string $kind = null,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        int $page = 1,
        int $perPage = 50,
    ): AuditEntryPage {
        $page = max(1, $page);
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
        $filter = new AuditEntryFilter($actorId, $targetId, $name, null === $kind ? null : AuditKind::tryFrom($kind), $from, $to);

        return new AuditEntryPage(
            array_map($this->view(...), $this->entries->search($filter, $page, $perPage)),
            $page,
            $perPage,
            $this->entries->count($filter),
        );
    }

    private function view(AuditEntry $entry): AuditEntryView
    {
        return new AuditEntryView(
            $entry->id->toString(),
            $entry->occurredAt,
            $entry->kind->value,
            $entry->name,
            $entry->actor->type->value,
            $entry->actor->userId,
            $entry->actor->role,
            $entry->targetId,
            $entry->payload,
            $entry->outcome->value,
            $entry->failureReason,
            $entry->correlationId,
            $entry->ip,
            $entry->userAgent,
        );
    }
}
