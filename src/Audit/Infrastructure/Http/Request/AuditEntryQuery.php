<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Http\Request;

use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of GET /api/v1/admin/audit.
 */
#[OA\Schema]
final readonly class AuditEntryQuery
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $actorId = null,
        #[Assert\Length(max: 64)]
        public ?string $targetId = null,
        #[Assert\Length(max: 160)]
        public ?string $name = null,
        #[Assert\Choice(choices: ['command', 'event'])]
        public ?string $kind = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 50,
    ) {
    }
}
