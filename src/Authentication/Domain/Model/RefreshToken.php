<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\SharedKernel\Domain\AggregateRoot;
use DateInterval;
use DateTimeImmutable;

/**
 * One refresh token of a token family. Logging in starts a family; every refresh replaces the token with the next
 * one in the same family. Presenting a token that was already replaced is reuse and revokes the whole family.
 * Only the hash of the token is kept.
 */
final class RefreshToken extends AggregateRoot
{
    private function __construct(
        private readonly RefreshTokenId $id,
        private readonly TokenFamilyId $familyId,
        private readonly AccountId $accountId,
        private readonly TokenHash $hash,
        private readonly DateTimeImmutable $issuedAt,
        private readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $rotatedAt,
        private ?DateTimeImmutable $revokedAt,
        private ?RefreshTokenId $replacedBy,
        private int $version,
    ) {
    }

    /**
     * Starts a new family (a login).
     */
    public static function issue(
        RefreshTokenId $id,
        TokenFamilyId $familyId,
        AccountId $accountId,
        TokenHash $hash,
        DateTimeImmutable $now,
        DateInterval $lifetime,
    ): self {
        return new self($id, $familyId, $accountId, $hash, $now, $now->add($lifetime), null, null, null, 0);
    }

    public static function reconstitute(
        RefreshTokenId $id,
        TokenFamilyId $familyId,
        AccountId $accountId,
        TokenHash $hash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $rotatedAt,
        ?DateTimeImmutable $revokedAt,
        ?RefreshTokenId $replacedBy,
        int $version,
    ): self {
        return new self($id, $familyId, $accountId, $hash, $issuedAt, $expiresAt, $rotatedAt, $revokedAt, $replacedBy, $version);
    }

    public function status(DateTimeImmutable $now): RefreshTokenStatus
    {
        return match (true) {
            null !== $this->revokedAt => RefreshTokenStatus::Revoked,
            null !== $this->rotatedAt => RefreshTokenStatus::Rotated,
            $now >= $this->expiresAt => RefreshTokenStatus::Expired,
            default => RefreshTokenStatus::Usable,
        };
    }

    /**
     * Replaces this token with the next one of the same family.
     *
     * @throws AuthenticationProblem when the token is expired, already rotated or revoked
     */
    public function rotate(RefreshTokenId $nextId, TokenHash $nextHash, DateTimeImmutable $now, DateInterval $lifetime): self
    {
        match ($this->status($now)) {
            RefreshTokenStatus::Usable => null,
            RefreshTokenStatus::Expired => throw AuthenticationProblem::tokenExpired(),
            RefreshTokenStatus::Rotated, RefreshTokenStatus::Revoked => throw AuthenticationProblem::tokenRevoked(),
        };

        $this->rotatedAt = $now;
        $this->replacedBy = $nextId;

        return new self($nextId, $this->familyId, $this->accountId, $nextHash, $now, $now->add($lifetime), null, null, null, 0);
    }

    public function revoke(DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }

    public function id(): RefreshTokenId
    {
        return $this->id;
    }

    public function familyId(): TokenFamilyId
    {
        return $this->familyId;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function hash(): TokenHash
    {
        return $this->hash;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function rotatedAt(): ?DateTimeImmutable
    {
        return $this->rotatedAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function replacedBy(): ?RefreshTokenId
    {
        return $this->replacedBy;
    }

    public function version(): int
    {
        return $this->version;
    }
}
