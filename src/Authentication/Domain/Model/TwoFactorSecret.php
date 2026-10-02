<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Event\TwoFactorEnabled;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\SharedKernel\Domain\AggregateRoot;
use DateTimeImmutable;

use function in_array;

/**
 * The TOTP secret of an admin (kept encrypted), its confirmation state, the single-use recovery codes (hashed)
 * and the last accepted time step, so a code can't be replayed.
 */
final class TwoFactorSecret extends AggregateRoot
{
    /**
     * @param list<TokenHash> $recoveryCodeHashes
     */
    private function __construct(
        private readonly TwoFactorSecretId $id,
        private readonly AccountId $accountId,
        private readonly string $encryptedSecret,
        private ?DateTimeImmutable $confirmedAt,
        private array $recoveryCodeHashes,
        private ?int $lastUsedStep,
    ) {
    }

    public static function enrol(TwoFactorSecretId $id, AccountId $accountId, string $encryptedSecret): self
    {
        return new self($id, $accountId, $encryptedSecret, null, [], null);
    }

    /**
     * @param list<TokenHash> $recoveryCodeHashes
     */
    public static function reconstitute(
        TwoFactorSecretId $id,
        AccountId $accountId,
        string $encryptedSecret,
        ?DateTimeImmutable $confirmedAt,
        array $recoveryCodeHashes,
        ?int $lastUsedStep,
    ): self {
        return new self($id, $accountId, $encryptedSecret, $confirmedAt, $recoveryCodeHashes, $lastUsedStep);
    }

    /**
     * Confirms the enrolment after the user proved they hold the secret, and stores the recovery codes.
     *
     * @param list<TokenHash> $recoveryCodeHashes
     * @param int             $usedStep           the time step of the code that confirmed the enrolment
     */
    public function confirm(array $recoveryCodeHashes, int $usedStep, DateTimeImmutable $now): void
    {
        $this->confirmedAt = $now;
        $this->recoveryCodeHashes = $recoveryCodeHashes;
        $this->lastUsedStep = $usedStep;
        $this->recordThat(new TwoFactorEnabled($this->accountId, $now));
    }

    public function isConfirmed(): bool
    {
        return null !== $this->confirmedAt;
    }

    /**
     * Remembers the time step of an accepted code. A step that is not newer than the last one is a replay.
     *
     * @throws AuthenticationProblem two-factor-invalid on a replayed step
     */
    public function acceptStep(int $step): void
    {
        if (null !== $this->lastUsedStep && $step <= $this->lastUsedStep) {
            throw AuthenticationProblem::twoFactorInvalid();
        }

        $this->lastUsedStep = $step;
    }

    /**
     * Uses up a recovery code.
     *
     * @return bool false when no unused recovery code matches
     */
    public function useRecoveryCode(TokenHash $hash): bool
    {
        $found = false;
        $remaining = [];
        foreach ($this->recoveryCodeHashes as $stored) {
            if (!$found && $stored->equals($hash)) {
                $found = true;

                continue;
            }
            $remaining[] = $stored;
        }

        if ($found) {
            $this->recoveryCodeHashes = $remaining;
        }

        return $found;
    }

    public function hasRecoveryCode(TokenHash $hash): bool
    {
        return in_array(true, array_map(static fn (TokenHash $stored): bool => $stored->equals($hash), $this->recoveryCodeHashes), true);
    }

    public function id(): TwoFactorSecretId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function encryptedSecret(): string
    {
        return $this->encryptedSecret;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    /**
     * @return list<TokenHash>
     */
    public function recoveryCodeHashes(): array
    {
        return $this->recoveryCodeHashes;
    }

    public function lastUsedStep(): ?int
    {
        return $this->lastUsedStep;
    }
}
