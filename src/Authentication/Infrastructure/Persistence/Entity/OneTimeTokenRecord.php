<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence\Entity;

use DateTimeImmutable;

/**
 * Doctrine record of a OneTimeToken (table authentication.one_time_token).
 */
final class OneTimeTokenRecord
{
    public string $id;
    public string $accountId;
    public string $purpose;
    public string $hash;
    public DateTimeImmutable $issuedAt;
    public DateTimeImmutable $expiresAt;
    public ?DateTimeImmutable $usedAt = null;
    public ?DateTimeImmutable $invalidatedAt = null;
}
