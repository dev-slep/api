<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Application;

use App\Authentication\Application\Port\AuthenticatedPrincipal;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Application\Port\AuthenticationSettings;
use App\Authentication\Application\Port\IssuedAccessToken;
use App\Authentication\Application\Port\VerifiedSocialIdentity;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthenticationSettings::class)]
#[CoversClass(AuthenticatedPrincipal::class)]
#[CoversClass(IssuedAccessToken::class)]
#[CoversClass(VerifiedSocialIdentity::class)]
final class ApplicationValueClassesTest extends TestCase
{
    public function testSettingsDefaults(): void
    {
        $settings = new AuthenticationSettings();

        self::assertSame(30, $settings->refreshTokenLifetime()->d);
        self::assertSame(24, $settings->emailVerificationLifetime()->h);
        self::assertSame(1, $settings->passwordResetLifetime()->h);
        self::assertSame(30, $settings->expiredTokenRetention()->d);
    }

    public function testSettingsCanBeConfigured(): void
    {
        $settings = new AuthenticationSettings('P7D', 'PT2H', 'PT15M', 'P1D');

        self::assertSame(7, $settings->refreshTokenLifetime()->d);
        self::assertSame(2, $settings->emailVerificationLifetime()->h);
        self::assertSame(15, $settings->passwordResetLifetime()->i);
        self::assertSame(1, $settings->expiredTokenRetention()->d);
    }

    public function testASettingsValueThatIsNotAnIntervalFailsLoudly(): void
    {
        $this->expectException(Exception::class);

        (new AuthenticationSettings('thirty days'))->refreshTokenLifetime();
    }

    public function testThePrincipalKnowsItsSecondFactorState(): void
    {
        $pending = new AuthenticatedPrincipal('id', [], AuthenticationMethod::PendingTwoFactor);
        $admin = new AuthenticatedPrincipal('id', ['ROLE_ADMIN'], AuthenticationMethod::PasswordAndOtp);
        $driver = new AuthenticatedPrincipal('id', ['ROLE_DRIVER'], AuthenticationMethod::Password);

        self::assertTrue($pending->isTwoFactorPending());
        self::assertFalse($pending->isTwoFactorVerified());
        self::assertFalse($admin->isTwoFactorPending());
        self::assertTrue($admin->isTwoFactorVerified());
        self::assertFalse($driver->isTwoFactorPending());
        self::assertFalse($driver->isTwoFactorVerified());
        self::assertSame(['ROLE_ADMIN'], $admin->roles);
        self::assertSame('id', $admin->accountId);
    }

    public function testIssuedTokenAndVerifiedIdentityKeepTheirValues(): void
    {
        $issued = new IssuedAccessToken('jwt', 900);
        $identity = new SocialIdentity(SocialProvider::Apple, new SocialSubject('s'));
        $verified = new VerifiedSocialIdentity($identity, new Email('a@example.com'), true);

        self::assertSame('jwt', $issued->token);
        self::assertSame(900, $issued->expiresInSeconds);
        self::assertSame($identity, $verified->identity);
        self::assertTrue($verified->emailVerified);
        self::assertSame('a@example.com', $verified->email->toString());
    }
}
