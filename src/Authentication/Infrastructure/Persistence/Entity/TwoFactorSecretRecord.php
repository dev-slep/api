<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence\Entity;

use DateTimeImmutable;

/**
 * Doctrine record of a TwoFactorSecret (table authentication.two_factor_secret), one per account.
 */
final class TwoFactorSecretRecord
{
    public string $id;
    public string $accountId;
    public string $encryptedSecret;
    public ?DateTimeImmutable $confirmedAt = null;

    /** @var list<string> hex SHA-256 hashes of the unused recovery codes */
    public array $recoveryCodeHashes = [];
    public ?int $lastUsedStep = null;
}
