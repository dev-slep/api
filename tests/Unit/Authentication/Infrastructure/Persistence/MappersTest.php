<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\AccountStatus;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenId;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\PasswordHash;
use App\Authentication\Domain\Model\PhoneNumber;
use App\Authentication\Domain\Model\RefreshToken;
use App\Authentication\Domain\Model\RefreshTokenId;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Model\TwoFactorSecret;
use App\Authentication\Domain\Model\TwoFactorSecretId;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Infrastructure\Persistence\Entity\OneTimeTokenRecord;
use App\Authentication\Infrastructure\Persistence\Entity\RefreshTokenRecord;
use App\Authentication\Infrastructure\Persistence\Entity\TwoFactorSecretRecord;
use App\Authentication\Infrastructure\Persistence\Entity\UserAccountRecord;
use App\Authentication\Infrastructure\Persistence\Mapper\OneTimeTokenMapper;
use App\Authentication\Infrastructure\Persistence\Mapper\RefreshTokenMapper;
use App\Authentication\Infrastructure\Persistence\Mapper\TwoFactorSecretMapper;
use App\Authentication\Infrastructure\Persistence\Mapper\UserAccountMapper;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(UserAccountMapper::class)]
#[CoversClass(RefreshTokenMapper::class)]
#[CoversClass(OneTimeTokenMapper::class)]
#[CoversClass(TwoFactorSecretMapper::class)]
final class MappersTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
    }

    private function uuid(int $n): string
    {
        return sprintf('01900000-0000-7000-8000-%012d', $n);
    }

    private function hash(string $seed): TokenHash
    {
        return new TokenHash(hash('sha256', $seed));
    }

    public function testAFullAccountSurvivesTheRoundTrip(): void
    {
        $account = UserAccount::reconstitute(
            new AccountId($this->uuid(1)),
            new Email('ana@example.com'),
            new PasswordHash('hash'),
            AccountRole::Tower,
            new PhoneNumber('+381641234567'),
            new Locale('sr_Latn'),
            $this->now->modify('+1 hour'),
            AccountStatus::Banned,
            [new SocialIdentity(SocialProvider::Google, new SocialSubject('g-1')), new SocialIdentity(SocialProvider::Apple, new SocialSubject('a-1'))],
            $this->now,
            $this->now->modify('+2 hours'),
        );
        $mapper = new UserAccountMapper();

        $record = $mapper->apply($account, new UserAccountRecord());
        $back = $mapper->toDomain($record);

        self::assertSame($this->uuid(1), $record->id);
        self::assertSame('ana@example.com', $record->email);
        self::assertSame('TOWER', $record->role);
        self::assertSame('BANNED', $record->status);
        self::assertCount(2, $record->socialIdentities);
        $first = $record->socialIdentities->first();
        self::assertNotFalse($first);
        self::assertSame($record, $first->account);
        self::assertEquals($account->id(), $back->id());
        self::assertTrue($back->email()->equals($account->email()));
        self::assertSame('hash', $back->passwordHash()?->value());
        self::assertSame(AccountRole::Tower, $back->role());
        self::assertSame('+381641234567', $back->phone()?->toString());
        self::assertSame('sr_Latn', $back->locale()->toString());
        self::assertEquals($this->now->modify('+1 hour'), $back->emailVerifiedAt());
        self::assertTrue($back->isBanned());
        self::assertCount(2, $back->socialIdentities());
        self::assertEquals($this->now, $back->registeredAt());
        self::assertEquals($this->now->modify('+2 hours'), $back->passwordChangedAt());
    }

    public function testAMinimalAccountSurvivesTheRoundTripWithNullsIntact(): void
    {
        $account = UserAccount::reconstitute(new AccountId($this->uuid(1)), new Email('a@example.com'), null, AccountRole::Driver, null, new Locale('en'), null, AccountStatus::Active, [], $this->now, null);
        $mapper = new UserAccountMapper();

        $back = $mapper->toDomain($mapper->apply($account, new UserAccountRecord()));

        self::assertNull($back->passwordHash());
        self::assertNull($back->phone());
        self::assertNull($back->emailVerifiedAt());
        self::assertNull($back->passwordChangedAt());
        self::assertSame([], $back->socialIdentities());
    }

    public function testApplyingTwiceDoesNotDuplicateSocialIdentities(): void
    {
        $account = UserAccount::reconstitute(new AccountId($this->uuid(1)), new Email('a@example.com'), null, AccountRole::Driver, null, new Locale('en'), null, AccountStatus::Active, [new SocialIdentity(SocialProvider::Google, new SocialSubject('g-1'))], $this->now, null);
        $mapper = new UserAccountMapper();
        $record = new UserAccountRecord();

        $mapper->apply($account, $record);
        $mapper->apply($account, $record);
        $account->linkSocialIdentity(new SocialIdentity(SocialProvider::Apple, new SocialSubject('a-1')), false, $this->now);
        $mapper->apply($account, $record);

        self::assertCount(2, $record->socialIdentities);
    }

    public function testARefreshTokenSurvivesTheRoundTripAndTheVersionIsLeftToDoctrine(): void
    {
        $token = RefreshToken::reconstitute(new RefreshTokenId($this->uuid(1)), new TokenFamilyId($this->uuid(2)), new AccountId($this->uuid(3)), $this->hash('x'), $this->now, $this->now->modify('+30 days'), $this->now->modify('+1 day'), $this->now->modify('+2 days'), new RefreshTokenId($this->uuid(4)), 9);
        $mapper = new RefreshTokenMapper();
        $record = new RefreshTokenRecord();
        $record->version = 3;

        $mapper->apply($token, $record);
        $back = $mapper->toDomain($record);

        self::assertSame(3, $record->version, 'the optimistic lock column is never overwritten from the aggregate');
        self::assertSame(3, $back->version());
        self::assertSame($this->uuid(1), $back->id()->toString());
        self::assertSame($this->uuid(2), $back->familyId()->toString());
        self::assertSame($this->uuid(3), $back->accountId()->toString());
        self::assertTrue($back->hash()->equals($this->hash('x')));
        self::assertEquals($this->now->modify('+1 day'), $back->rotatedAt());
        self::assertEquals($this->now->modify('+2 days'), $back->revokedAt());
        self::assertSame($this->uuid(4), $back->replacedBy()?->toString());
    }

    public function testAFreshRefreshTokenKeepsItsNulls(): void
    {
        $token = RefreshToken::issue(new RefreshTokenId($this->uuid(1)), new TokenFamilyId($this->uuid(2)), new AccountId($this->uuid(3)), $this->hash('x'), $this->now, new DateInterval('P30D'));
        $mapper = new RefreshTokenMapper();

        $back = $mapper->toDomain($mapper->apply($token, new RefreshTokenRecord()));

        self::assertNull($back->rotatedAt());
        self::assertNull($back->revokedAt());
        self::assertNull($back->replacedBy());
    }

    public function testAOneTimeTokenSurvivesTheRoundTrip(): void
    {
        $token = OneTimeToken::reconstitute(new OneTimeTokenId($this->uuid(1)), new AccountId($this->uuid(2)), OneTimeTokenPurpose::PasswordReset, $this->hash('y'), $this->now, $this->now->modify('+1 hour'), $this->now->modify('+5 minutes'), $this->now->modify('+6 minutes'));
        $mapper = new OneTimeTokenMapper();

        $record = $mapper->apply($token, new OneTimeTokenRecord());
        $back = $mapper->toDomain($record);

        self::assertSame('PASSWORD_RESET', $record->purpose);
        self::assertSame(OneTimeTokenPurpose::PasswordReset, $back->purpose());
        self::assertSame($this->uuid(2), $back->accountId()->toString());
        self::assertTrue($back->hash()->equals($this->hash('y')));
        self::assertEquals($this->now->modify('+5 minutes'), $back->usedAt());
        self::assertEquals($this->now->modify('+6 minutes'), $back->invalidatedAt());
    }

    public function testAFreshOneTimeTokenKeepsItsNulls(): void
    {
        $token = OneTimeToken::issue(new OneTimeTokenId($this->uuid(1)), new AccountId($this->uuid(2)), OneTimeTokenPurpose::EmailVerification, $this->hash('y'), $this->now, new DateInterval('PT1H'));
        $mapper = new OneTimeTokenMapper();

        $back = $mapper->toDomain($mapper->apply($token, new OneTimeTokenRecord()));

        self::assertNull($back->usedAt());
        self::assertNull($back->invalidatedAt());
    }

    public function testATwoFactorSecretSurvivesTheRoundTrip(): void
    {
        $secret = TwoFactorSecret::reconstitute(new TwoFactorSecretId($this->uuid(1)), new AccountId($this->uuid(2)), 'enc', $this->now, [$this->hash('a'), $this->hash('b')], 123);
        $mapper = new TwoFactorSecretMapper();

        $record = $mapper->apply($secret, new TwoFactorSecretRecord());
        $back = $mapper->toDomain($record);

        self::assertSame([hash('sha256', 'a'), hash('sha256', 'b')], $record->recoveryCodeHashes);
        self::assertSame('enc', $back->encryptedSecret());
        self::assertEquals($this->now, $back->confirmedAt());
        self::assertSame(123, $back->lastUsedStep());
        self::assertTrue($back->hasRecoveryCode($this->hash('b')));
    }

    public function testAnUnconfirmedTwoFactorSecretKeepsItsNulls(): void
    {
        $secret = TwoFactorSecret::enrol(new TwoFactorSecretId($this->uuid(1)), new AccountId($this->uuid(2)), 'enc');
        $mapper = new TwoFactorSecretMapper();

        $back = $mapper->toDomain($mapper->apply($secret, new TwoFactorSecretRecord()));

        self::assertNull($back->confirmedAt());
        self::assertNull($back->lastUsedStep());
        self::assertSame([], $back->recoveryCodeHashes());
    }
}
