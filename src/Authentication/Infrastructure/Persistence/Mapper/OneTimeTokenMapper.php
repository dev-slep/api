<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence\Mapper;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenId;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Infrastructure\Persistence\Entity\OneTimeTokenRecord;

final readonly class OneTimeTokenMapper
{
    public function toDomain(OneTimeTokenRecord $record): OneTimeToken
    {
        return OneTimeToken::reconstitute(
            new OneTimeTokenId($record->id),
            new AccountId($record->accountId),
            OneTimeTokenPurpose::from($record->purpose),
            new TokenHash($record->hash),
            $record->issuedAt,
            $record->expiresAt,
            $record->usedAt,
            $record->invalidatedAt,
        );
    }

    public function apply(OneTimeToken $token, OneTimeTokenRecord $record): OneTimeTokenRecord
    {
        $record->id = $token->id()->toString();
        $record->accountId = $token->accountId()->toString();
        $record->purpose = $token->purpose()->value;
        $record->hash = $token->hash()->value();
        $record->issuedAt = $token->issuedAt();
        $record->expiresAt = $token->expiresAt();
        $record->usedAt = $token->usedAt();
        $record->invalidatedAt = $token->invalidatedAt();

        return $record;
    }
}
