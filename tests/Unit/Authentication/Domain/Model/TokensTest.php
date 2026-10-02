<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Domain\Model;

use App\Authentication\Domain\Event\TwoFactorEnabled;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenId;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\RefreshToken;
use App\Authentication\Domain\Model\RefreshTokenId;
use App\Authentication\Domain\Model\RefreshTokenStatus;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Model\TwoFactorSecret;
use App\Authentication\Domain\Model\TwoFactorSecretId;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(RefreshToken::class)]
#[CoversClass(OneTimeToken::class)]
#[CoversClass(TwoFactorSecret::class)]
#[CoversClass(TwoFactorEnabled::class)]
final class TokensTest extends TestCase
{
    private const string ACCOUNT = '01900000-0000-7000-8000-0000000000aa';

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
    }

    private function id(int $n): string
    {
        return sprintf('01900000-0000-7000-8000-%012d', $n);
    }

    private function hash(string $seed): TokenHash
    {
        return new TokenHash(hash('sha256', $seed));
    }

    private function refreshToken(): RefreshToken
    {
        return RefreshToken::issue(new RefreshTokenId($this->id(1)), new TokenFamilyId($this->id(2)), new AccountId(self::ACCOUNT), $this->hash('one'), $this->now, new DateInterval('P30D'));
    }

    public function testAFreshRefreshTokenIsUsableUntilItExpires(): void
    {
        $token = $this->refreshToken();

        self::assertSame($this->id(1), $token->id()->toString());
        self::assertSame($this->id(2), $token->familyId()->toString());
        self::assertSame(self::ACCOUNT, $token->accountId()->toString());
        self::assertTrue($token->hash()->equals($this->hash('one')));
        self::assertEquals($this->now, $token->issuedAt());
        self::assertEquals($this->now->modify('+30 days'), $token->expiresAt());
        self::assertNull($token->rotatedAt());
        self::assertNull($token->revokedAt());
        self::assertNull($token->replacedBy());
        self::assertSame(0, $token->version());
        self::assertSame(RefreshTokenStatus::Usable, $token->status($this->now));
        self::assertSame(RefreshTokenStatus::Usable, $token->status($this->now->modify('+30 days -1 second')));
    }

    public function testARefreshTokenExpiresExactlyAtItsExpiryTime(): void
    {
        $token = $this->refreshToken();

        self::assertSame(RefreshTokenStatus::Expired, $token->status($this->now->modify('+30 days')));
        self::assertSame(RefreshTokenStatus::Expired, $token->status($this->now->modify('+30 days +1 second')));
    }

    public function testRotatingReturnsTheNextTokenOfTheSameFamily(): void
    {
        $token = $this->refreshToken();
        $later = $this->now->modify('+1 day');

        $next = $token->rotate(new RefreshTokenId($this->id(3)), $this->hash('two'), $later, new DateInterval('P30D'));

        self::assertSame($this->id(3), $next->id()->toString());
        self::assertSame($this->id(2), $next->familyId()->toString());
        self::assertSame(self::ACCOUNT, $next->accountId()->toString());
        self::assertEquals($later->modify('+30 days'), $next->expiresAt());
        self::assertSame(RefreshTokenStatus::Usable, $next->status($later));
        self::assertSame(RefreshTokenStatus::Rotated, $token->status($later));
        self::assertEquals($later, $token->rotatedAt());
        self::assertSame($this->id(3), $token->replacedBy()?->toString());
    }

    public function testRotatingAnAlreadyRotatedTokenIsRejected(): void
    {
        $token = $this->refreshToken();
        $token->rotate(new RefreshTokenId($this->id(3)), $this->hash('two'), $this->now, new DateInterval('P30D'));

        $this->expectException(AuthenticationProblem::class);
        $this->expectExceptionMessage('revoked');

        $token->rotate(new RefreshTokenId($this->id(4)), $this->hash('three'), $this->now, new DateInterval('P30D'));
    }

    public function testRotatingAnExpiredTokenIsRejectedAndChangesNothing(): void
    {
        $token = $this->refreshToken();

        try {
            $token->rotate(new RefreshTokenId($this->id(3)), $this->hash('two'), $this->now->modify('+30 days'), new DateInterval('P30D'));
            self::fail('An expired token was rotated.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('token-expired', $problem->problemSlug());
        }
        self::assertNull($token->rotatedAt());
        self::assertNull($token->replacedBy());
    }

    public function testRotatingARevokedTokenIsRejected(): void
    {
        $token = $this->refreshToken();
        $token->revoke($this->now);

        $this->expectException(AuthenticationProblem::class);

        $token->rotate(new RefreshTokenId($this->id(3)), $this->hash('two'), $this->now, new DateInterval('P30D'));
    }

    public function testRevokingKeepsTheFirstRevocationTime(): void
    {
        $token = $this->refreshToken();

        $token->revoke($this->now->modify('+1 hour'));
        $token->revoke($this->now->modify('+2 hours'));

        self::assertEquals($this->now->modify('+1 hour'), $token->revokedAt());
        self::assertSame(RefreshTokenStatus::Revoked, $token->status($this->now));
    }

    public function testARevokedTokenCountsAsRevokedEvenAfterItsExpiry(): void
    {
        $token = $this->refreshToken();
        $token->revoke($this->now);

        self::assertSame(RefreshTokenStatus::Revoked, $token->status($this->now->modify('+31 days')));
    }

    public function testReconstitutingARefreshTokenKeepsEverything(): void
    {
        $token = RefreshToken::reconstitute(new RefreshTokenId($this->id(1)), new TokenFamilyId($this->id(2)), new AccountId(self::ACCOUNT), $this->hash('one'), $this->now, $this->now->modify('+1 day'), $this->now, null, new RefreshTokenId($this->id(3)), 7);

        self::assertSame(7, $token->version());
        self::assertSame(RefreshTokenStatus::Rotated, $token->status($this->now));
        self::assertSame($this->id(3), $token->replacedBy()?->toString());
        self::assertSame([], $token->releaseEvents());
    }

    private function oneTimeToken(): OneTimeToken
    {
        return OneTimeToken::issue(new OneTimeTokenId($this->id(10)), new AccountId(self::ACCOUNT), OneTimeTokenPurpose::PasswordReset, $this->hash('reset'), $this->now, new DateInterval('PT1H'));
    }

    public function testAOneTimeTokenCanBeUsedOnceBeforeItExpires(): void
    {
        $token = $this->oneTimeToken();

        self::assertSame($this->id(10), $token->id()->toString());
        self::assertSame(self::ACCOUNT, $token->accountId()->toString());
        self::assertSame(OneTimeTokenPurpose::PasswordReset, $token->purpose());
        self::assertTrue($token->hash()->equals($this->hash('reset')));
        self::assertEquals($this->now, $token->issuedAt());
        self::assertEquals($this->now->modify('+1 hour'), $token->expiresAt());
        self::assertTrue($token->isUsable($this->now->modify('+1 hour -1 second')));

        $token->consume($this->now->modify('+10 minutes'));

        self::assertEquals($this->now->modify('+10 minutes'), $token->usedAt());
        self::assertFalse($token->isUsable($this->now->modify('+11 minutes')));
    }

    public function testAOneTimeTokenExpiresExactlyAtItsExpiryTime(): void
    {
        $token = $this->oneTimeToken();

        self::assertFalse($token->isUsable($this->now->modify('+1 hour')));
        $this->expectException(AuthenticationProblem::class);

        $token->consume($this->now->modify('+1 hour'));
    }

    public function testAUsedOneTimeTokenCannotBeUsedAgain(): void
    {
        $token = $this->oneTimeToken();
        $token->consume($this->now);

        try {
            $token->consume($this->now);
            self::fail('A token was used twice.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('verification-token-invalid', $problem->problemSlug());
        }
    }

    public function testAnInvalidatedOneTimeTokenCannotBeUsed(): void
    {
        $token = $this->oneTimeToken();

        $token->invalidate($this->now);
        $token->invalidate($this->now->modify('+5 minutes'));

        self::assertEquals($this->now, $token->invalidatedAt());
        self::assertFalse($token->isUsable($this->now));
        $this->expectException(AuthenticationProblem::class);
        $token->consume($this->now);
    }

    public function testReconstitutingAOneTimeToken(): void
    {
        $token = OneTimeToken::reconstitute(new OneTimeTokenId($this->id(10)), new AccountId(self::ACCOUNT), OneTimeTokenPurpose::EmailVerification, $this->hash('x'), $this->now, $this->now->modify('+1 day'), $this->now, null);

        self::assertSame(OneTimeTokenPurpose::EmailVerification, $token->purpose());
        self::assertFalse($token->isUsable($this->now));
    }

    private function secret(): TwoFactorSecret
    {
        return TwoFactorSecret::enrol(new TwoFactorSecretId($this->id(20)), new AccountId(self::ACCOUNT), 'encrypted');
    }

    public function testAnEnrolmentStartsUnconfirmed(): void
    {
        $secret = $this->secret();

        self::assertSame($this->id(20), $secret->id()->toString());
        self::assertSame(self::ACCOUNT, $secret->accountId()->toString());
        self::assertSame('encrypted', $secret->encryptedSecret());
        self::assertFalse($secret->isConfirmed());
        self::assertNull($secret->confirmedAt());
        self::assertSame([], $secret->recoveryCodeHashes());
        self::assertNull($secret->lastUsedStep());
        self::assertSame([], $secret->releaseEvents());
    }

    public function testConfirmingStoresTheRecoveryCodesAndTheStepAndRecordsAnEvent(): void
    {
        $secret = $this->secret();
        $codes = [$this->hash('a'), $this->hash('b')];

        $secret->confirm($codes, 1234, $this->now);

        self::assertTrue($secret->isConfirmed());
        self::assertEquals($this->now, $secret->confirmedAt());
        self::assertSame($codes, $secret->recoveryCodeHashes());
        self::assertSame(1234, $secret->lastUsedStep());
        $events = $secret->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TwoFactorEnabled::class, $events[0]);
        self::assertEquals($this->now, $events[0]->occurredAt());
    }

    public function testAStepNewerThanTheLastOneIsAccepted(): void
    {
        $secret = $this->secret();
        $secret->confirm([], 100, $this->now);

        $secret->acceptStep(101);

        self::assertSame(101, $secret->lastUsedStep());
    }

    public function testTheSameOrAnOlderStepIsAReplay(): void
    {
        $secret = $this->secret();
        $secret->confirm([], 100, $this->now);

        foreach ([100, 99] as $step) {
            try {
                $secret->acceptStep($step);
                self::fail('A replayed step was accepted.');
            } catch (AuthenticationProblem $problem) {
                self::assertSame('two-factor-invalid', $problem->problemSlug());
            }
        }
        self::assertSame(100, $secret->lastUsedStep());
    }

    public function testAnyStepIsAcceptedWhenNoneWasUsedYet(): void
    {
        $secret = $this->secret();

        $secret->acceptStep(5);

        self::assertSame(5, $secret->lastUsedStep());
    }

    public function testARecoveryCodeWorksOnce(): void
    {
        $secret = $this->secret();
        $secret->confirm([$this->hash('a'), $this->hash('b')], 1, $this->now);

        self::assertTrue($secret->hasRecoveryCode($this->hash('a')));
        self::assertTrue($secret->useRecoveryCode($this->hash('a')));
        self::assertFalse($secret->hasRecoveryCode($this->hash('a')));
        self::assertFalse($secret->useRecoveryCode($this->hash('a')));
        self::assertTrue($secret->useRecoveryCode($this->hash('b')));
        self::assertSame([], $secret->recoveryCodeHashes());
    }

    public function testAnUnknownRecoveryCodeIsRejected(): void
    {
        $secret = $this->secret();
        $secret->confirm([$this->hash('a')], 1, $this->now);

        self::assertFalse($secret->useRecoveryCode($this->hash('zzz')));
        self::assertCount(1, $secret->recoveryCodeHashes());
    }

    public function testReconstitutingATwoFactorSecret(): void
    {
        $secret = TwoFactorSecret::reconstitute(new TwoFactorSecretId($this->id(20)), new AccountId(self::ACCOUNT), 'enc', $this->now, [$this->hash('a')], 55);

        self::assertTrue($secret->isConfirmed());
        self::assertSame(55, $secret->lastUsedStep());
        self::assertSame([], $secret->releaseEvents());
    }
}
