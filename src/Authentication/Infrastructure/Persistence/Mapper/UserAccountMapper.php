<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Persistence\Mapper;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\AccountStatus;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\PasswordHash;
use App\Authentication\Domain\Model\PhoneNumber;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Infrastructure\Persistence\Entity\SocialIdentityRecord;
use App\Authentication\Infrastructure\Persistence\Entity\UserAccountRecord;

/**
 * Record ⇄ aggregate. {@see self::apply()} copies the aggregate onto an existing (managed) record or a new one.
 */
final readonly class UserAccountMapper
{
    public function toDomain(UserAccountRecord $record): UserAccount
    {
        $identities = [];
        foreach ($record->socialIdentities as $identity) {
            $identities[] = new SocialIdentity(SocialProvider::from($identity->provider), new SocialSubject($identity->subject));
        }

        return UserAccount::reconstitute(
            new AccountId($record->id),
            new Email($record->email),
            null === $record->passwordHash ? null : new PasswordHash($record->passwordHash),
            AccountRole::from($record->role),
            null === $record->phone ? null : new PhoneNumber($record->phone),
            new Locale($record->locale),
            $record->emailVerifiedAt,
            AccountStatus::from($record->status),
            $identities,
            $record->registeredAt,
            $record->passwordChangedAt,
        );
    }

    public function apply(UserAccount $account, UserAccountRecord $record): UserAccountRecord
    {
        $record->id = $account->id()->toString();
        $record->email = $account->email()->toString();
        $record->passwordHash = $account->passwordHash()?->value();
        $record->role = $account->role()->value;
        $record->phone = $account->phone()?->toString();
        $record->locale = $account->locale()->toString();
        $record->emailVerifiedAt = $account->emailVerifiedAt();
        $record->status = $account->status()->value;
        $record->registeredAt = $account->registeredAt();
        $record->passwordChangedAt = $account->passwordChangedAt();

        foreach ($account->socialIdentities() as $identity) {
            if ($this->hasIdentity($record, $identity)) {
                continue;
            }

            $identityRecord = new SocialIdentityRecord();
            $identityRecord->provider = $identity->provider->value;
            $identityRecord->subject = $identity->subject->toString();
            $identityRecord->account = $record;
            $record->socialIdentities->add($identityRecord);
        }

        return $record;
    }

    private function hasIdentity(UserAccountRecord $record, SocialIdentity $identity): bool
    {
        foreach ($record->socialIdentities as $existing) {
            if ($existing->provider === $identity->provider->value && $existing->subject === $identity->subject->toString()) {
                return true;
            }
        }

        return false;
    }
}
