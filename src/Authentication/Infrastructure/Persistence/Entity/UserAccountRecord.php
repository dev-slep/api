<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Doctrine record of a UserAccount (table authentication.user_account). Never leaves the Infrastructure layer.
 */
final class UserAccountRecord
{
    public string $id;
    public string $email;
    public ?string $passwordHash = null;
    public string $role;
    public ?string $phone = null;
    public string $locale;
    public ?DateTimeImmutable $emailVerifiedAt = null;
    public string $status;
    public DateTimeImmutable $registeredAt;
    public ?DateTimeImmutable $passwordChangedAt = null;

    /** @var Collection<int, SocialIdentityRecord> */
    public Collection $socialIdentities;

    public function __construct()
    {
        $this->socialIdentities = new ArrayCollection();
    }
}
