<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence\Entity;

use DateTimeImmutable;

/**
 * Doctrine record of a RefreshToken (table authentication.refresh_token). `version` is the optimistic lock.
 */
final class RefreshTokenRecord
{
    public string $id;
    public string $familyId;
    public string $accountId;
    public string $hash;
    public DateTimeImmutable $issuedAt;
    public DateTimeImmutable $expiresAt;
    public ?DateTimeImmutable $rotatedAt = null;
    public ?DateTimeImmutable $revokedAt = null;
    public ?string $replacedBy = null;
    public int $version = 1;
}
