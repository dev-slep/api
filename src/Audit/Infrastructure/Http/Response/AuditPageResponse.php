<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Http\Response;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['items', 'page', 'perPage', 'total'])]
final readonly class AuditPageResponse
{
    /**
     * @param list<AuditEntryResponse> $items newest first
     */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: AuditEntryResponse::class)))]
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {
    }
}
