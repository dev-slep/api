<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence\Entity;

/**
 * A linked social identity (table authentication.social_identity); (provider, subject) is globally unique.
 */
final class SocialIdentityRecord
{
    public string $provider;
    public string $subject;
    public UserAccountRecord $account;
}
