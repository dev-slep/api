<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Event\EmailVerified;
use App\Authentication\Domain\Event\PasswordChanged;
use App\Authentication\Domain\Event\UserRegistered;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\SharedKernel\Domain\AggregateRoot;
use DateTimeImmutable;

use function in_array;

final class UserAccount extends AggregateRoot
{
    /**
     * @param list<SocialIdentity> $socialIdentities
     */
    private function __construct(
        private readonly AccountId $id,
        private readonly Email $email,
        private ?PasswordHash $passwordHash,
        private readonly AccountRole $role,
        private readonly ?PhoneNumber $phone,
        private readonly Locale $locale,
        private ?DateTimeImmutable $emailVerifiedAt,
        private AccountStatus $status,
        private array $socialIdentities,
        private readonly DateTimeImmutable $registeredAt,
        private ?DateTimeImmutable $passwordChangedAt,
    ) {
    }

    public static function registerWithPassword(
        AccountId $id,
        Email $email,
        PasswordHash $passwordHash,
        AccountRole $role,
        ?PhoneNumber $phone,
        Locale $locale,
        DateTimeImmutable $now,
    ): self {
        if (!$role->isSelfRegistrable()) {
            throw AuthenticationProblem::adminRegistrationForbidden();
        }

        $account = new self($id, $email, $passwordHash, $role, $phone, $locale, null, AccountStatus::Active, [], $now, null);
        $account->recordThat(new UserRegistered($id, $email, $role, $phone, $locale, false, $now));

        return $account;
    }

    public static function registerWithSocialIdentity(
        AccountId $id,
        Email $email,
        AccountRole $role,
        ?PhoneNumber $phone,
        Locale $locale,
        SocialIdentity $identity,
        bool $emailVerifiedByProvider,
        DateTimeImmutable $now,
    ): self {
        if (!$role->isSelfRegistrable()) {
            throw AuthenticationProblem::adminRegistrationForbidden();
        }

        $verifiedAt = $emailVerifiedByProvider ? $now : null;
        $account = new self($id, $email, null, $role, $phone, $locale, $verifiedAt, AccountStatus::Active, [$identity], $now, null);
        $account->recordThat(new UserRegistered($id, $email, $role, $phone, $locale, $emailVerifiedByProvider, $now));

        return $account;
    }

    /**
     * Admins are created by an operator (console command), never through the public API. Their email counts as verified.
     */
    public static function createAdmin(AccountId $id, Email $email, PasswordHash $passwordHash, Locale $locale, DateTimeImmutable $now): self
    {
        $account = new self($id, $email, $passwordHash, AccountRole::Admin, null, $locale, $now, AccountStatus::Active, [], $now, null);
        $account->recordThat(new UserRegistered($id, $email, AccountRole::Admin, null, $locale, true, $now));

        return $account;
    }

    /**
     * @param list<SocialIdentity> $socialIdentities
     */
    public static function reconstitute(
        AccountId $id,
        Email $email,
        ?PasswordHash $passwordHash,
        AccountRole $role,
        ?PhoneNumber $phone,
        Locale $locale,
        ?DateTimeImmutable $emailVerifiedAt,
        AccountStatus $status,
        array $socialIdentities,
        DateTimeImmutable $registeredAt,
        ?DateTimeImmutable $passwordChangedAt,
    ): self {
        return new self($id, $email, $passwordHash, $role, $phone, $locale, $emailVerifiedAt, $status, $socialIdentities, $registeredAt, $passwordChangedAt);
    }

    /**
     * Idempotent: a second verification changes nothing and records no event.
     */
    public function verifyEmail(DateTimeImmutable $now): void
    {
        if (null !== $this->emailVerifiedAt) {
            return;
        }

        $this->emailVerifiedAt = $now;
        $this->recordThat(new EmailVerified($this->id, $now));
    }

    public function changePassword(PasswordHash $newHash, DateTimeImmutable $now): void
    {
        $this->passwordHash = $newHash;
        $this->passwordChangedAt = $now;
        $this->recordThat(new PasswordChanged($this->id, $now));
    }

    /**
     * Replaces the stored hash with a stronger one computed from the same password (no event: nothing changed for the user).
     */
    public function upgradePasswordHash(PasswordHash $newHash): void
    {
        $this->passwordHash = $newHash;
    }

    /**
     * Drops the password of an account whose email was never confirmed. Whoever registered it may not own the address,
     * so once the real owner proves it (for example through a social provider) that password must stop working.
     */
    public function discardUnconfirmedPassword(): void
    {
        if ($this->isEmailVerified()) {
            return;
        }

        $this->passwordHash = null;
    }

    /**
     * Links another social identity. A provider that confirms the email also confirms ownership of it.
     */
    public function linkSocialIdentity(SocialIdentity $identity, bool $emailVerifiedByProvider, DateTimeImmutable $now): void
    {
        if ($this->hasSocialIdentity($identity)) {
            throw AuthenticationProblem::identityAlreadyLinked();
        }

        $this->socialIdentities[] = $identity;

        if ($emailVerifiedByProvider) {
            $this->verifyEmail($now);
        }
    }

    public function ban(): void
    {
        $this->status = AccountStatus::Banned;
    }

    /**
     * @throws AuthenticationProblem when the account may not log in (banned, or the email is not verified yet)
     */
    public function assertCanLogIn(): void
    {
        if (AccountStatus::Banned === $this->status) {
            throw AuthenticationProblem::accountBanned();
        }
        if (!$this->isEmailVerified()) {
            throw AuthenticationProblem::emailNotVerified();
        }
    }

    public function hasSocialIdentity(SocialIdentity $identity): bool
    {
        return in_array(true, array_map(static fn (SocialIdentity $linked): bool => $linked->equals($identity), $this->socialIdentities), true);
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): ?PasswordHash
    {
        return $this->passwordHash;
    }

    public function hasPassword(): bool
    {
        return null !== $this->passwordHash;
    }

    public function role(): AccountRole
    {
        return $this->role;
    }

    public function phone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function locale(): Locale
    {
        return $this->locale;
    }

    public function isEmailVerified(): bool
    {
        return null !== $this->emailVerifiedAt;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function isBanned(): bool
    {
        return AccountStatus::Banned === $this->status;
    }

    /**
     * @return list<SocialIdentity>
     */
    public function socialIdentities(): array
    {
        return $this->socialIdentities;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function passwordChangedAt(): ?DateTimeImmutable
    {
        return $this->passwordChangedAt;
    }
}
