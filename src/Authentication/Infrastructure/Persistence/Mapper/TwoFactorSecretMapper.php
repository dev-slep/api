<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence\Mapper;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Model\TwoFactorSecret;
use App\Authentication\Domain\Model\TwoFactorSecretId;
use App\Authentication\Infrastructure\Persistence\Entity\TwoFactorSecretRecord;

final readonly class TwoFactorSecretMapper
{
    public function toDomain(TwoFactorSecretRecord $record): TwoFactorSecret
    {
        return TwoFactorSecret::reconstitute(
            new TwoFactorSecretId($record->id),
            new AccountId($record->accountId),
            $record->encryptedSecret,
            $record->confirmedAt,
            array_map(static fn (string $hash): TokenHash => new TokenHash($hash), $record->recoveryCodeHashes),
            $record->lastUsedStep,
        );
    }

    public function apply(TwoFactorSecret $secret, TwoFactorSecretRecord $record): TwoFactorSecretRecord
    {
        $record->id = $secret->id()->toString();
        $record->accountId = $secret->accountId()->toString();
        $record->encryptedSecret = $secret->encryptedSecret();
        $record->confirmedAt = $secret->confirmedAt();
        $record->recoveryCodeHashes = array_map(static fn (TokenHash $hash): string => $hash->value(), $secret->recoveryCodeHashes());
        $record->lastUsedStep = $secret->lastUsedStep();

        return $record;
    }
}
