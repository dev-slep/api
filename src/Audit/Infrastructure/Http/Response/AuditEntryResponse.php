<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Http\Response;

use OpenApi\Attributes as OA;
use stdClass;

#[OA\Schema(required: ['id', 'occurredAt', 'kind', 'name', 'actorType', 'payload', 'outcome'])]
final readonly class AuditEntryResponse
{
    /**
     * @param array<string, mixed>|stdClass $payload secrets are masked; an object even when empty, so it is `{}` in JSON
     */
    public function __construct(
        public string $id,
        public string $occurredAt,
        #[OA\Property(enum: ['command', 'event'])]
        public string $kind,
        public string $name,
        #[OA\Property(enum: ['user', 'anonymous', 'system'])]
        public string $actorType,
        public ?string $actorId,
        public ?string $actorRole,
        public ?string $targetId,
        #[OA\Property(type: 'object', additionalProperties: true)]
        public array|stdClass $payload,
        #[OA\Property(enum: ['success', 'failure', 'recorded'])]
        public string $outcome,
        public ?string $failureReason,
        public ?string $correlationId,
        public ?string $ip,
        public ?string $userAgent,
    ) {
    }
}
