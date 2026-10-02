<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\SharedKernel\Domain\AggregateRoot;
use DateInterval;
use DateTimeImmutable;

/**
 * A single-use, expiring token sent by email (email verification or password reset). Only its hash is kept.
 */
final class OneTimeToken extends AggregateRoot
{
    private function __construct(
        private readonly OneTimeTokenId $id,
        private readonly AccountId $accountId,
        private readonly OneTimeTokenPurpose $purpose,
        private readonly TokenHash $hash,
        private readonly DateTimeImmutable $issuedAt,
        private readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $usedAt,
        private ?DateTimeImmutable $invalidatedAt,
    ) {
    }

    public static function issue(
        OneTimeTokenId $id,
        AccountId $accountId,
        OneTimeTokenPurpose $purpose,
        TokenHash $hash,
        DateTimeImmutable $now,
        DateInterval $lifetime,
    ): self {
        return new self($id, $accountId, $purpose, $hash, $now, $now->add($lifetime), null, null);
    }

    public static function reconstitute(
        OneTimeTokenId $id,
        AccountId $accountId,
        OneTimeTokenPurpose $purpose,
        TokenHash $hash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $usedAt,
        ?DateTimeImmutable $invalidatedAt,
    ): self {
        return new self($id, $accountId, $purpose, $hash, $issuedAt, $expiresAt, $usedAt, $invalidatedAt);
    }

    /**
     * Marks the token as used.
     *
     * @throws AuthenticationProblem verification-token-invalid when it was used, replaced or has expired
     */
    public function consume(DateTimeImmutable $now): void
    {
        if (!$this->isUsable($now)) {
            throw AuthenticationProblem::verificationTokenInvalid();
        }

        $this->usedAt = $now;
    }

    public function invalidate(DateTimeImmutable $now): void
    {
        $this->invalidatedAt ??= $now;
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && null === $this->invalidatedAt && $now < $this->expiresAt;
    }

    public function id(): OneTimeTokenId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function purpose(): OneTimeTokenPurpose
    {
        return $this->purpose;
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

    public function usedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function invalidatedAt(): ?DateTimeImmutable
    {
        return $this->invalidatedAt;
    }
}
