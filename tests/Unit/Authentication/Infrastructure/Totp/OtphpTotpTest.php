<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Totp;

use App\Authentication\Domain\Model\Email;
use App\Authentication\Infrastructure\Totp\OtphpTotp;
use DateTimeImmutable;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OtphpTotp::class)]
final class OtphpTotpTest extends TestCase
{
    /** RFC 6238 test secret ("12345678901234567890") */
    private const string SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private static function at(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }

    /**
     * @return iterable<int|string, array{int, string}>
     */
    public static function rfcVectors(): iterable
    {
        // RFC 6238 appendix B, SHA-1, last 6 of the 8-digit values
        yield 'T=59' => [59, '287082'];
        yield 'T=1111111109' => [1111111109, '081804'];
        yield 'T=1111111111' => [1111111111, '050471'];
        yield 'T=1234567890' => [1234567890, '005924'];
        yield 'T=2000000000' => [2000000000, '279037'];
    }

    #[DataProvider('rfcVectors')]
    public function testTheRfcTestVectorsAreAccepted(int $timestamp, string $code): void
    {
        $step = (new OtphpTotp())->verify(self::SECRET, $code, self::at($timestamp));

        self::assertSame(intdiv($timestamp, 30), $step);
    }

    public function testOneStepOfDriftEitherWayIsAcceptedAndReportsTheCodesOwnStep(): void
    {
        $totp = new OtphpTotp();

        self::assertSame(intdiv(1111111111, 30) - 1, $totp->verify(self::SECRET, '081804', self::at(1111111111)), 'a code from the previous step');
        self::assertSame(intdiv(1111111109, 30) + 1, $totp->verify(self::SECRET, '050471', self::at(1111111109)), 'a code from the next step');
    }

    public function testTwoStepsOfDriftAreRejected(): void
    {
        $totp = new OtphpTotp();

        // The code is for step 1 (T=59): at T=149 the window covers steps 3 to 5
        self::assertNull($totp->verify(self::SECRET, '287082', self::at(59 + 90)));
        self::assertNull($totp->verify(self::SECRET, '081804', self::at(1111111109 - 90)));
    }

    public function testTheWindowEdgesWithTheFrozenClock(): void
    {
        $totp = new OtphpTotp();
        $code = TOTP::createFromSecret(self::SECRET, null)->at(30000);

        // The code belongs to the step starting at 30000; the window accepts the clock from one step before to one step after
        self::assertNotNull($totp->verify(self::SECRET, $code, self::at(30000 - 30)));
        self::assertNotNull($totp->verify(self::SECRET, $code, self::at(30000 + 59)));
        self::assertNull($totp->verify(self::SECRET, $code, self::at(30000 + 60)));
        self::assertNull($totp->verify(self::SECRET, $code, self::at(30000 - 31)));
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function malformedCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'five digits' => ['12345'];
        yield 'seven digits' => ['1234567'];
        yield 'letters' => ['abcdef'];
        yield 'recovery code' => ['abcd-efgh-jklm'];
        yield 'sql' => ["1' OR '1"];
    }

    #[DataProvider('malformedCodes')]
    public function testMalformedCodesAreRejected(string $code): void
    {
        self::assertNull((new OtphpTotp())->verify(self::SECRET, $code, self::at(59)));
    }

    public function testSpacesInsideACodeAreIgnored(): void
    {
        self::assertNotNull((new OtphpTotp())->verify(self::SECRET, '287 082', self::at(59)));
    }

    public function testAWrongCodeIsRejected(): void
    {
        self::assertNull((new OtphpTotp())->verify(self::SECRET, '000000', self::at(59)));
    }

    public function testGeneratedSecretsAreRandomBase32(): void
    {
        $totp = new OtphpTotp();
        $first = $totp->generateSecret();

        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $first, '32 base32 characters are 160 bits, as RFC 4226 recommends');
        self::assertNotSame($first, $totp->generateSecret());
    }

    public function testTheProvisioningUriNamesTheIssuerAndTheAccount(): void
    {
        $uri = (new OtphpTotp('Slep'))->provisioningUri(self::SECRET, new Email('root@example.com'));

        self::assertStringStartsWith('otpauth://totp/Slep%3Aroot%40example.com?', $uri);
        self::assertStringContainsString('secret='.self::SECRET, $uri);
        self::assertStringContainsString('issuer=Slep', $uri);
        // The defaults every authenticator app assumes (30-second steps, 6 digits, SHA-1) are left out of the URI
        self::assertStringNotContainsString('period=', $uri);
        self::assertStringNotContainsString('digits=', $uri);
    }
}
