<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\InvalidValue;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\OpaqueToken;
use App\Authentication\Domain\Model\PasswordHash;
use App\Authentication\Domain\Model\PhoneNumber;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use App\Authentication\Domain\Model\TokenHash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;

#[CoversClass(Email::class)]
#[CoversClass(PlainPassword::class)]
#[CoversClass(PasswordHash::class)]
#[CoversClass(PhoneNumber::class)]
#[CoversClass(SocialSubject::class)]
#[CoversClass(SocialIdentity::class)]
#[CoversClass(OpaqueToken::class)]
#[CoversClass(TokenHash::class)]
#[CoversClass(AccountRole::class)]
#[CoversClass(Locale::class)]
#[CoversClass(InvalidValue::class)]
final class ValueObjectsTest extends TestCase
{
    public function testEmailIsTrimmedAndLowercased(): void
    {
        $email = new Email('  Ana.Petrovic@Example.COM ');

        self::assertSame('ana.petrovic@example.com', $email->toString());
        self::assertSame('ana.petrovic@example.com', (string) $email);
    }

    public function testEmailsAreEqualIgnoringCase(): void
    {
        self::assertTrue((new Email('ANA@example.com'))->equals(new Email('ana@EXAMPLE.com')));
        self::assertFalse((new Email('ana@example.com'))->equals(new Email('bob@example.com')));
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'no at sign' => ['ana.example.com'];
        yield 'no domain' => ['ana@'];
        yield 'no local part' => ['@example.com'];
        yield 'spaces inside' => ['an a@example.com'];
        yield 'two at signs' => ['a@b@example.com'];
        yield 'too long' => [str_repeat('a', 250).'@example.com'];
    }

    #[DataProvider('invalidEmails')]
    public function testInvalidEmailsAreRejected(string $value): void
    {
        $this->expectException(InvalidValue::class);

        new Email($value);
    }

    public function testEmailAtTheLengthLimitIsAccepted(): void
    {
        $local = str_repeat('a', 64);
        $domain = str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57).'.com';
        $value = $local.'@'.$domain;

        self::assertSame($value, (new Email($value))->toString());
    }

    public function testPasswordIsReadableOnlyThroughReveal(): void
    {
        $password = new PlainPassword('s3cret passphrase');

        self::assertSame('s3cret passphrase', $password->reveal());
        self::assertSame(17, $password->length());
        self::assertSame('***', (string) $password);
        self::assertSame(['value' => '***'], $password->__debugInfo());
        self::assertStringNotContainsString('s3cret', print_r($password, true));
        ob_start();
        var_dump($password);
        self::assertStringNotContainsString('s3cret', (string) ob_get_clean());
    }

    public function testPasswordCannotBeSerialised(): void
    {
        $this->expectException(InvalidValue::class);

        serialize(new PlainPassword('s3cret passphrase'));
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function invalidPasswords(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ["  \t "];
        yield 'one too long' => [str_repeat('a', 129)];
    }

    #[DataProvider('invalidPasswords')]
    public function testInvalidPasswordsAreRejected(string $value): void
    {
        $this->expectException(InvalidValue::class);

        new PlainPassword($value);
    }

    public function testPasswordAtTheLengthLimitsIsAccepted(): void
    {
        self::assertSame(128, (new PlainPassword(str_repeat('é', 128)))->length());
        self::assertSame(1, (new PlainPassword('x'))->length());
    }

    public function testPasswordHash(): void
    {
        $hash = new PasswordHash('$argon2id$v=19$abc');

        self::assertSame('$argon2id$v=19$abc', $hash->value());
        self::assertTrue($hash->equals(new PasswordHash('$argon2id$v=19$abc')));
        self::assertFalse($hash->equals(new PasswordHash('$argon2id$v=19$abd')));
    }

    public function testEmptyPasswordHashIsRejected(): void
    {
        $this->expectException(InvalidValue::class);

        new PasswordHash('');
    }

    /**
     * @return iterable<int|string, array{string, string}>
     */
    public static function phoneNumbers(): iterable
    {
        yield 'international' => ['+381 64 123 4567', '+381641234567'];
        yield 'with dashes and brackets' => ['(064) 123-4567', '0641234567'];
        yield 'dots' => ['+381.64.1234567', '+381641234567'];
        yield 'seven digits' => ['1234567', '1234567'];
        yield 'fifteen digits' => ['+123456789012345', '+123456789012345'];
    }

    #[DataProvider('phoneNumbers')]
    public function testPhoneNumbersAreNormalised(string $input, string $expected): void
    {
        self::assertSame($expected, (new PhoneNumber($input))->toString());
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function invalidPhoneNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'letters' => ['064abc4567'];
        yield 'six digits' => ['123456'];
        yield 'sixteen digits' => ['+1234567890123456'];
        yield 'plus in the middle' => ['381+641234567'];
    }

    #[DataProvider('invalidPhoneNumbers')]
    public function testInvalidPhoneNumbersAreRejected(string $value): void
    {
        $this->expectException(InvalidValue::class);

        new PhoneNumber($value);
    }

    public function testPhoneNumberEqualityAndStringForm(): void
    {
        $phone = new PhoneNumber('+381 64 1234567');

        self::assertTrue($phone->equals(new PhoneNumber('+381641234567')));
        self::assertFalse($phone->equals(new PhoneNumber('+381641234568')));
        self::assertSame('+381641234567', (string) $phone);
    }

    public function testSocialSubjectAndIdentity(): void
    {
        $subject = new SocialSubject('1234567890');
        $identity = new SocialIdentity(SocialProvider::Google, $subject);

        self::assertSame('1234567890', $subject->toString());
        self::assertSame('1234567890', (string) $subject);
        self::assertTrue($subject->equals(new SocialSubject('1234567890')));
        self::assertFalse($subject->equals(new SocialSubject('1234567891')));
        self::assertTrue($identity->equals(new SocialIdentity(SocialProvider::Google, new SocialSubject('1234567890'))));
        self::assertFalse($identity->equals(new SocialIdentity(SocialProvider::Apple, new SocialSubject('1234567890'))));
        self::assertFalse($identity->equals(new SocialIdentity(SocialProvider::Google, new SocialSubject('other'))));
    }

    public function testSocialSubjectLimits(): void
    {
        self::assertSame(255, strlen((new SocialSubject(str_repeat('s', 255)))->toString()));

        $this->expectException(InvalidValue::class);
        new SocialSubject(str_repeat('s', 256));
    }

    public function testEmptySocialSubjectIsRejected(): void
    {
        $this->expectException(InvalidValue::class);

        new SocialSubject('');
    }

    public function testOpaqueTokenIsHiddenFromDumps(): void
    {
        $token = new OpaqueToken('abcdefghij-ABCDEFGHIJ_0123456789');

        self::assertSame('abcdefghij-ABCDEFGHIJ_0123456789', $token->reveal());
        self::assertSame('***', (string) $token);
        self::assertSame(['value' => '***'], $token->__debugInfo());
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function invalidTokens(): iterable
    {
        yield 'too short' => [str_repeat('a', 19)];
        yield 'too long' => [str_repeat('a', 257)];
        yield 'illegal character' => [str_repeat('a', 20).'+'];
        yield 'space' => [str_repeat('a', 10).' '.str_repeat('a', 10)];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidTokens')]
    public function testInvalidTokensAreRejected(string $value): void
    {
        $this->expectException(InvalidValue::class);

        new OpaqueToken($value);
    }

    public function testTokenLengthBoundariesAreAccepted(): void
    {
        self::assertSame(20, strlen((new OpaqueToken(str_repeat('a', 20)))->reveal()));
        self::assertSame(256, strlen((new OpaqueToken(str_repeat('a', 256)))->reveal()));
    }

    public function testTokenHash(): void
    {
        $hash = new TokenHash(strtoupper(hash('sha256', 'x')));

        self::assertSame(hash('sha256', 'x'), $hash->value());
        self::assertTrue($hash->equals(new TokenHash(hash('sha256', 'x'))));
        self::assertFalse($hash->equals(new TokenHash(hash('sha256', 'y'))));
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function invalidHashes(): iterable
    {
        yield 'too short' => [str_repeat('a', 63)];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'not hex' => [str_repeat('g', 64)];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidHashes')]
    public function testInvalidTokenHashesAreRejected(string $value): void
    {
        $this->expectException(InvalidValue::class);

        new TokenHash($value);
    }

    public function testRoles(): void
    {
        self::assertTrue(AccountRole::Driver->isSelfRegistrable());
        self::assertTrue(AccountRole::Tower->isSelfRegistrable());
        self::assertFalse(AccountRole::Admin->isSelfRegistrable());
        self::assertSame('ROLE_DRIVER', AccountRole::Driver->securityRole());
        self::assertSame('ROLE_TOWER', AccountRole::Tower->securityRole());
        self::assertSame('ROLE_ADMIN', AccountRole::Admin->securityRole());
    }

    public function testLocale(): void
    {
        self::assertSame('sr_Latn', (new Locale('sr_Latn'))->toString());
        self::assertSame('en', (string) new Locale('en'));
        self::assertTrue((new Locale('en'))->equals(new Locale('en')));
        self::assertFalse((new Locale('en'))->equals(new Locale('sr_Latn')));
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function invalidLocales(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase language' => ['EN'];
        yield 'dash' => ['sr-Latn'];
        yield 'too long language' => ['eng'];
        yield 'injection' => ["en\nBcc: x@y.z"];
    }

    #[DataProvider('invalidLocales')]
    public function testInvalidLocalesAreRejected(string $value): void
    {
        $this->expectException(InvalidValue::class);

        new Locale($value);
    }

    public function testInvalidValueCarriesItsReason(): void
    {
        self::assertSame('because', InvalidValue::because('because')->getMessage());
    }
}
