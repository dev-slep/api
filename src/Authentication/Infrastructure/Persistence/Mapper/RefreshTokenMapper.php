<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence\Mapper;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\RefreshToken;
use App\Authentication\Domain\Model\RefreshTokenId;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Infrastructure\Persistence\Entity\RefreshTokenRecord;

final readonly class RefreshTokenMapper
{
    public function toDomain(RefreshTokenRecord $record): RefreshToken
    {
        return RefreshToken::reconstitute(
            new RefreshTokenId($record->id),
            new TokenFamilyId($record->familyId),
            new AccountId($record->accountId),
            new TokenHash($record->hash),
            $record->issuedAt,
            $record->expiresAt,
            $record->rotatedAt,
            $record->revokedAt,
            null === $record->replacedBy ? null : new RefreshTokenId($record->replacedBy),
            $record->version,
        );
    }

    /**
     * The version column is owned by Doctrine (optimistic lock) and is never written from the aggregate.
     */
    public function apply(RefreshToken $token, RefreshTokenRecord $record): RefreshTokenRecord
    {
        $record->id = $token->id()->toString();
        $record->familyId = $token->familyId()->toString();
        $record->accountId = $token->accountId()->toString();
        $record->hash = $token->hash()->value();
        $record->issuedAt = $token->issuedAt();
        $record->expiresAt = $token->expiresAt();
        $record->rotatedAt = $token->rotatedAt();
        $record->revokedAt = $token->revokedAt();
        $record->replacedBy = $token->replacedBy()?->toString();

        return $record;
    }
}
