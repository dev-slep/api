<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Crypto;

use App\Authentication\Domain\Model\PasswordHash;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Infrastructure\Crypto\Argon2idPasswordHasher;
use App\Authentication\Infrastructure\Crypto\LibsodiumSecretEncrypter;
use App\Authentication\Infrastructure\Crypto\RandomTokenGenerator;
use App\Authentication\Infrastructure\Crypto\Sha256TokenHasher;

use function chr;
use function count;
use function ord;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Argon2idPasswordHasher::class)]
#[CoversClass(RandomTokenGenerator::class)]
#[CoversClass(Sha256TokenHasher::class)]
#[CoversClass(LibsodiumSecretEncrypter::class)]
final class CryptoTest extends TestCase
{
    public function testAPasswordIsHashedWithArgon2idAndVerified(): void
    {
        $hasher = new Argon2idPasswordHasher(8, 1);

        $hash = $hasher->hash(new PlainPassword('correct horse'));

        self::assertStringStartsWith('$argon2id$', $hash->value());
        self::assertTrue($hasher->verify(new PlainPassword('correct horse'), $hash));
        self::assertFalse($hasher->verify(new PlainPassword('correct horsf'), $hash));
        self::assertFalse($hasher->verify(new PlainPassword('Correct horse'), $hash));
    }

    public function testTheSamePasswordGetsADifferentHashEachTime(): void
    {
        $hasher = new Argon2idPasswordHasher(8, 1);

        self::assertNotSame($hasher->hash(new PlainPassword('same password'))->value(), $hasher->hash(new PlainPassword('same password'))->value());
    }

    public function testUnicodePasswordsRoundTrip(): void
    {
        $hasher = new Argon2idPasswordHasher(8, 1);
        $password = new PlainPassword('лозинка-šđčćž-密码');

        self::assertTrue($hasher->verify($password, $hasher->hash($password)));
    }

    public function testAHashFromWeakerParametersNeedsRehashing(): void
    {
        $weak = new Argon2idPasswordHasher(8, 1);
        $strong = new Argon2idPasswordHasher(16, 2);
        $hash = $weak->hash(new PlainPassword('correct horse'));

        self::assertFalse($weak->needsRehash($hash));
        self::assertTrue($strong->needsRehash($hash));
    }

    public function testAForeignHashFormatNeedsRehashingAndNeverVerifiesAnything(): void
    {
        $hasher = new Argon2idPasswordHasher(8, 1);
        $foreign = new PasswordHash('not a real hash');

        self::assertTrue($hasher->needsRehash($foreign));
        self::assertFalse($hasher->verify(new PlainPassword('not a real hash'), $foreign));
    }

    public function testGeneratedTokensAreLongUniqueAndUrlSafe(): void
    {
        $generator = new RandomTokenGenerator();
        $seen = [];

        for ($i = 0; $i < 50; ++$i) {
            $token = $generator->generate()->reveal();
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
            $seen[$token] = true;
        }

        self::assertCount(50, $seen);
    }

    public function testRecoveryCodesHaveTheDocumentedFormat(): void
    {
        $generator = new RandomTokenGenerator();
        $seen = [];

        for ($i = 0; $i < 50; ++$i) {
            $code = $generator->recoveryCode();
            self::assertMatchesRegularExpression('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/', $code);
            self::assertDoesNotMatchRegularExpression('/[lo01]/', $code, 'ambiguous characters are left out');
            $seen[$code] = true;
        }

        self::assertGreaterThan(45, count($seen));
    }

    public function testTokensAreHashedWithSha256(): void
    {
        $hasher = new Sha256TokenHasher();

        self::assertSame(hash('sha256', 'token'), $hasher->hash('token')->value());
        self::assertSame(hash('sha256', ''), $hasher->hash('')->value());
        self::assertFalse($hasher->hash('a')->equals($hasher->hash('b')));
    }

    private function encrypter(string $keySeed = 'k'): LibsodiumSecretEncrypter
    {
        return new LibsodiumSecretEncrypter(base64_encode(str_repeat($keySeed, 32)));
    }

    public function testASecretRoundTripsAndIsNotStoredInTheClear(): void
    {
        $encrypter = $this->encrypter();

        $encrypted = $encrypter->encrypt('JBSWY3DPEHPK3PXP');

        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $encrypted);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', (string) base64_decode($encrypted, true));
        self::assertSame('JBSWY3DPEHPK3PXP', $encrypter->decrypt($encrypted));
        self::assertNotSame($encrypted, $encrypter->encrypt('JBSWY3DPEHPK3PXP'), 'every encryption uses a fresh nonce');
    }

    public function testAnEmptySecretRoundTrips(): void
    {
        self::assertSame('', $this->encrypter()->decrypt($this->encrypter()->encrypt('')));
    }

    public function testATamperedValueIsRejected(): void
    {
        $encrypter = $this->encrypter();
        $raw = (string) base64_decode($encrypter->encrypt('secret'), true);
        $raw = substr($raw, 0, -1).chr(ord(substr($raw, -1)) ^ 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be decrypted');

        $encrypter->decrypt(base64_encode($raw));
    }

    public function testAValueEncryptedWithAnotherKeyIsRejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->encrypter('x')->decrypt($this->encrypter('k')->encrypt('secret'));
    }

    public function testMalformedValuesAreRejected(): void
    {
        foreach (['', '%%%not base64%%%', base64_encode('short')] as $value) {
            try {
                $this->encrypter()->decrypt($value);
                self::fail("Accepted: $value");
            } catch (RuntimeException $failure) {
                self::assertStringContainsString('malformed', $failure->getMessage());
            }
        }
    }

    public function testTheKeyMustBe32BytesOfBase64(): void
    {
        foreach ([base64_encode('too short'), '%%%', ''] as $key) {
            try {
                new LibsodiumSecretEncrypter($key);
                self::fail('A bad key was accepted.');
            } catch (RuntimeException $failure) {
                self::assertStringContainsString('32 random bytes', $failure->getMessage());
            }
        }
    }
}
