<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Jwt;

use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Infrastructure\Jwt\JwtAccessTokenIssuer;
use App\Authentication\Infrastructure\Jwt\JwtAccessTokenVerifier;
use App\Authentication\Infrastructure\Jwt\JwtSettings;
use App\Authentication\Infrastructure\Jwt\PsrClockAdapter;
use App\Tests\Support\Authentication\RsaKey;
use App\Tests\Support\Fake\FrozenClock;
use App\Tests\Support\Fake\SequentialIdGenerator;

use function array_slice;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JwtAccessTokenIssuer::class)]
#[CoversClass(JwtAccessTokenVerifier::class)]
#[CoversClass(JwtSettings::class)]
#[CoversClass(PsrClockAdapter::class)]
final class JwtTest extends TestCase
{
    private const string ACCOUNT = '01900000-0000-7000-8000-0000000000aa';

    private string $directory;

    /** @var non-empty-string */
    private string $privateKey;

    /** @var non-empty-string */
    private string $publicKey;

    /** @var non-empty-string */
    private string $otherPublicKey;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jwt-test-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
        [$this->privateKey, $this->publicKey] = $this->keyPair('a');
        [, $this->otherPublicKey] = $this->keyPair('b');
        $this->clock = new FrozenClock('2026-01-01T12:00:00+00:00');
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /**
     * @return array{non-empty-string, non-empty-string} private and public key paths
     */
    private function keyPair(string $name): array
    {
        $key = RsaKey::generate();
        $private = $this->directory."/$name-private.pem";
        $public = $this->directory."/$name-public.pem";
        file_put_contents($private, $key->privatePem());
        file_put_contents($public, $key->publicPem());

        return [$private, $public];
    }

    /**
     * @param array<array-key, non-empty-string>|null $publicKeys
     * @param non-empty-string                        $keyId
     * @param non-empty-string|null                   $privateKey
     */
    private function settings(?array $publicKeys = null, string $keyId = '1', ?string $privateKey = null): JwtSettings
    {
        return new JwtSettings($privateKey ?? $this->privateKey, $publicKeys ?? [$keyId => $this->publicKey], $keyId, 'https://slep.test', 'slep-api', 900, 300);
    }

    private function issuer(?JwtSettings $settings = null): JwtAccessTokenIssuer
    {
        return new JwtAccessTokenIssuer($settings ?? $this->settings(), $this->clock, new SequentialIdGenerator());
    }

    private function verifier(?JwtSettings $settings = null): JwtAccessTokenVerifier
    {
        return new JwtAccessTokenVerifier($settings ?? $this->settings(), $this->clock);
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $claims
     */
    private function forged(array $header, array $claims, string $signature = ''): string
    {
        return $this->base64url((string) json_encode($header)).'.'.$this->base64url((string) json_encode($claims)).'.'.$this->base64url($signature);
    }

    private function assertRejected(string $token, string $slug): void
    {
        try {
            $this->verifier()->verify($token);
            self::fail('The token was accepted.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame($slug, $problem->problemSlug());
            self::assertSame(401, $problem->httpStatus());
        }
    }

    public function testAnIssuedTokenVerifiesAndCarriesTheClaims(): void
    {
        $issued = $this->issuer()->issue(new AccountId(self::ACCOUNT), ['ROLE_DRIVER', 'ROLE_TOWER'], AuthenticationMethod::Password);

        $principal = $this->verifier()->verify($issued->token);

        self::assertSame(900, $issued->expiresInSeconds);
        self::assertSame(self::ACCOUNT, $principal->accountId);
        self::assertSame(['ROLE_DRIVER', 'ROLE_TOWER'], $principal->roles);
        self::assertSame(AuthenticationMethod::Password, $principal->method);
    }

    public function testTheTokenHasTheExpectedHeaderAndRegisteredClaims(): void
    {
        $token = $this->issuer()->issue(new AccountId(self::ACCOUNT), ['ROLE_DRIVER'], AuthenticationMethod::Social)->token;
        [$headerPart, $claimsPart] = array_slice(explode('.', $token), 0, 2);
        $header = json_decode((string) base64_decode(strtr($headerPart, '-_', '+/'), true), true);
        $claims = json_decode((string) base64_decode(strtr($claimsPart, '-_', '+/'), true), true);
        self::assertIsArray($header);
        self::assertIsArray($claims);

        self::assertSame(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => '1'], $header);
        self::assertSame('https://slep.test', $claims['iss']);
        self::assertSame(['slep-api'], (array) $claims['aud']);
        self::assertSame(self::ACCOUNT, $claims['sub']);
        self::assertSame($this->clock->now()->getTimestamp(), $claims['iat']);
        self::assertSame($this->clock->now()->getTimestamp(), $claims['nbf']);
        self::assertSame($this->clock->now()->getTimestamp() + 900, $claims['exp']);
        self::assertSame('01900000-0000-7000-8000-000000000001', $claims['jti']);
        self::assertSame('social', $claims['amr']);
        self::assertSame(['ROLE_DRIVER'], $claims['roles']);
    }

    public function testEveryTokenGetsItsOwnId(): void
    {
        $issuer = $this->issuer();
        $one = $issuer->issue(new AccountId(self::ACCOUNT), [], AuthenticationMethod::Password)->token;
        $two = $issuer->issue(new AccountId(self::ACCOUNT), [], AuthenticationMethod::Password)->token;

        self::assertNotSame($one, $two);
    }

    public function testAPendingTokenIsShortLivedAndKeepsItsMethod(): void
    {
        $issued = $this->issuer()->issue(new AccountId(self::ACCOUNT), [], AuthenticationMethod::PendingTwoFactor);

        self::assertSame(300, $issued->expiresInSeconds);
        $principal = $this->verifier()->verify($issued->token);
        self::assertTrue($principal->isTwoFactorPending());
        self::assertSame([], $principal->roles);
    }

    public function testTheTokenIsValidUpToTheSecondBeforeItsExpiryAndExpiredFromThatSecondOn(): void
    {
        $token = $this->issuer()->issue(new AccountId(self::ACCOUNT), ['ROLE_DRIVER'], AuthenticationMethod::Password)->token;

        $this->clock->advance('+899 seconds');
        self::assertSame(self::ACCOUNT, $this->verifier()->verify($token)->accountId);

        $this->clock->advance('+1 second');
        $this->assertRejected($token, 'token-expired');

        $this->clock->advance('+1 day');
        $this->assertRejected($token, 'token-expired');
    }

    public function testATokenIsNotValidBeforeItsNotBeforeTime(): void
    {
        $token = $this->issuer()->issue(new AccountId(self::ACCOUNT), ['ROLE_DRIVER'], AuthenticationMethod::Password)->token;
        $this->clock->set((new DateTimeImmutable('2026-01-01T12:00:00+00:00'))->modify('-1 second'));

        $this->assertRejected($token, 'token-invalid');
    }

    public function testATokenSignedWithAnotherKeyIsRejected(): void
    {
        $token = $this->issuer()->issue(new AccountId(self::ACCOUNT), ['ROLE_ADMIN'], AuthenticationMethod::PasswordAndOtp)->token;

        $this->expectException(AuthenticationProblem::class);

        $this->verifier($this->settings(['1' => $this->otherPublicKey]))->verify($token);
    }

    public function testATamperedPayloadIsRejected(): void
    {
        $token = $this->issuer()->issue(new AccountId(self::ACCOUNT), ['ROLE_DRIVER'], AuthenticationMethod::Password)->token;
        [$header, , $signature] = explode('.', $token);
        $payload = $this->base64url((string) json_encode(['sub' => self::ACCOUNT, 'roles' => ['ROLE_ADMIN'], 'amr' => 'pwd+otp', 'iss' => 'https://slep.test', 'aud' => ['slep-api'], 'exp' => $this->clock->now()->getTimestamp() + 900, 'iat' => $this->clock->now()->getTimestamp(), 'nbf' => $this->clock->now()->getTimestamp()]));

        $this->assertRejected($header.'.'.$payload.'.'.$signature, 'token-invalid');
    }

    public function testAnUnsignedAlgNoneTokenIsRejected(): void
    {
        $claims = ['sub' => self::ACCOUNT, 'roles' => ['ROLE_ADMIN'], 'amr' => 'pwd+otp', 'iss' => 'https://slep.test', 'aud' => ['slep-api'], 'exp' => $this->clock->now()->getTimestamp() + 900, 'iat' => $this->clock->now()->getTimestamp(), 'nbf' => $this->clock->now()->getTimestamp()];

        $this->assertRejected($this->forged(['typ' => 'JWT', 'alg' => 'none', 'kid' => '1'], $claims), 'token-invalid');
    }

    public function testAnHmacTokenSignedWithThePublicKeyIsRejected(): void
    {
        $claims = ['sub' => self::ACCOUNT, 'roles' => ['ROLE_ADMIN'], 'amr' => 'pwd+otp', 'iss' => 'https://slep.test', 'aud' => ['slep-api'], 'exp' => $this->clock->now()->getTimestamp() + 900, 'iat' => $this->clock->now()->getTimestamp(), 'nbf' => $this->clock->now()->getTimestamp()];
        $header = $this->base64url((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256', 'kid' => '1']));
        $payload = $this->base64url((string) json_encode($claims));
        $signature = $this->base64url(hash_hmac('sha256', $header.'.'.$payload, (string) file_get_contents($this->publicKey), true));

        $this->assertRejected($header.'.'.$payload.'.'.$signature, 'token-invalid');
    }

    public function testAnUnknownKeyIdIsRejected(): void
    {
        $token = $this->issuer($this->settings(null, 'ghost'))->issue(new AccountId(self::ACCOUNT), [], AuthenticationMethod::Password)->token;

        $this->assertRejected($token, 'token-invalid');
    }

    public function testATokenSignedBeforeAKeyRotationStaysValidWhileTheOldPublicKeyIsKept(): void
    {
        [$newPrivate, $newPublic] = $this->keyPair('rotated');
        $oldToken = $this->issuer($this->settings(['1' => $this->publicKey], '1'))->issue(new AccountId(self::ACCOUNT), ['ROLE_DRIVER'], AuthenticationMethod::Password)->token;
        $newToken = $this->issuer($this->settings(null, '2', $newPrivate))->issue(new AccountId(self::ACCOUNT), ['ROLE_DRIVER'], AuthenticationMethod::Password)->token;
        $rotated = $this->verifier($this->settings(['1' => $this->publicKey, '2' => $newPublic], '2'));

        self::assertSame(self::ACCOUNT, $rotated->verify($oldToken)->accountId);
        self::assertSame(self::ACCOUNT, $rotated->verify($newToken)->accountId);
        $this->expectException(AuthenticationProblem::class);
        $this->verifier($this->settings(['2' => $newPublic], '2'))->verify($oldToken);
    }

    public function testAWrongIssuerOrAudienceIsRejected(): void
    {
        $token = $this->issuer()->issue(new AccountId(self::ACCOUNT), [], AuthenticationMethod::Password)->token;
        $otherIssuer = new JwtSettings($this->privateKey, ['1' => $this->publicKey], '1', 'https://other.test', 'slep-api');
        $otherAudience = new JwtSettings($this->privateKey, ['1' => $this->publicKey], '1', 'https://slep.test', 'other-api');

        foreach ([$otherIssuer, $otherAudience] as $settings) {
            try {
                $this->verifier($settings)->verify($token);
                self::fail('The token was accepted.');
            } catch (AuthenticationProblem $problem) {
                self::assertSame('token-invalid', $problem->problemSlug());
            }
        }
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function garbage(): iterable
    {
        yield 'empty' => [''];
        yield 'not a jwt' => ['hello'];
        yield 'two parts' => ['a.b'];
        yield 'four parts' => ['a.b.c.d'];
        yield 'invalid base64' => ['%%%.%%%.%%%'];
        yield 'not json' => ['bm90LWpzb24.bm90LWpzb24.c2ln'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('garbage')]
    public function testGarbageIsRejected(string $token): void
    {
        $this->assertRejected($token, 'token-invalid');
    }

    public function testClaimsOfTheWrongShapeAreRejected(): void
    {
        $issued = $this->issuer()->issue(new AccountId(self::ACCOUNT), ['ROLE_DRIVER'], AuthenticationMethod::Password)->token;
        self::assertNotSame('', $issued);
        // A correctly signed token whose "amr" is unknown: sign it ourselves with the real key
        $builder = \Lcobucci\JWT\Token\Builder::new(new \Lcobucci\JWT\Encoding\JoseEncoder(), \Lcobucci\JWT\Encoding\ChainedFormatter::withUnixTimestampDates());
        $token = $builder->issuedBy('https://slep.test')->permittedFor('slep-api')->relatedTo(self::ACCOUNT)
            ->issuedAt($this->clock->now())->canOnlyBeUsedAfter($this->clock->now())->expiresAt($this->clock->now()->modify('+1 hour'))
            ->withHeader('kid', '1')->withClaim('roles', ['ROLE_ADMIN'])->withClaim('amr', 'magic')
            ->getToken(new \Lcobucci\JWT\Signer\Rsa\Sha256(), \Lcobucci\JWT\Signer\Key\InMemory::file($this->privateKey))->toString();

        $this->assertRejected($token, 'token-invalid');
    }

    public function testThePsrClockAdapterReadsTheApplicationClock(): void
    {
        self::assertEquals($this->clock->now(), (new PsrClockAdapter($this->clock))->now());
    }

    public function testEmptySettingsAreRejected(): void
    {
        foreach ([[' ', '1', 'iss', 'aud'], ['private', ' ', 'iss', 'aud'], ['private', '1', ' ', 'aud'], ['private', '1', 'iss', ' ']] as [$private, $keyId, $issuer, $audience]) {
            try {
                new JwtSettings($private, ['1' => 'public'], $keyId, $issuer, $audience);
                self::fail('Blank settings were accepted.');
            } catch (InvalidArgumentException $failure) {
                self::assertStringContainsString('must not be empty', $failure->getMessage());
            }
        }
    }

    public function testSettingsKeepTheirValues(): void
    {
        $settings = new JwtSettings('private', ['1' => 'public'], '1', 'iss', 'aud', 60, 30, 'secret');

        self::assertSame('private', $settings->privateKeyPath);
        self::assertSame(['1' => 'public'], $settings->publicKeyPaths);
        self::assertSame('1', $settings->currentKeyId);
        self::assertSame('iss', $settings->issuer);
        self::assertSame('aud', $settings->audience);
        self::assertSame(60, $settings->ttlSeconds);
        self::assertSame(30, $settings->pendingTtlSeconds);
        self::assertSame('secret', $settings->privateKeyPassphrase);
    }
}
