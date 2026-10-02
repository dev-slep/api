<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Social;

use App\Authentication\Application\Port\SocialProviderUnavailable;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Infrastructure\Social\JwksSocialIdentityVerifier;
use App\Authentication\Infrastructure\Social\RsaJwk;
use App\Authentication\Infrastructure\Social\SocialProviderSettings;
use App\Tests\Support\Authentication\RsaKey;
use App\Tests\Support\Fake\FrozenClock;
use DateTimeImmutable;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(JwksSocialIdentityVerifier::class)]
#[CoversClass(RsaJwk::class)]
#[CoversClass(SocialProviderSettings::class)]
final class SocialVerifierTest extends TestCase
{
    private const string AUDIENCE = 'client-id.apps.googleusercontent.com';
    private const string NONCE = 'raw-nonce-0123456789abcdef';

    private RsaKey $key;
    private FrozenClock $clock;

    /** @var list<string> */
    private array $requests = [];

    protected function setUp(): void
    {
        $this->key = RsaKey::generate();
        $this->clock = new FrozenClock('2026-01-01T12:00:00+00:00');
    }

    /**
     * @param non-empty-string                        $issuer
     * @param non-empty-string|list<non-empty-string> $audience
     * @param non-empty-string                        $keyId
     */
    private function token(
        string $issuer = 'https://accounts.google.com',
        string|array $audience = self::AUDIENCE,
        ?string $subject = 'google-subject-1',
        ?string $email = 'Ana@Example.com',
        bool|string|null $emailVerified = true,
        ?DateTimeImmutable $expiresAt = null,
        string $keyId = 'key-1',
        ?RsaKey $signWith = null,
        bool $withKeyId = true,
        ?string $nonce = null, // null: the digest of NONCE, '': no claim
        bool $withExpiry = true,
        bool $withIssuedAt = true,
        ?DateTimeImmutable $issuedAt = null,
        ?string $authorizedParty = null,
        bool|string|null $privateEmail = null,
    ): string {
        $builder = Builder::new(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates())
            ->issuedBy($issuer)
            ->permittedFor(...(array) $audience);
        if ($withExpiry) {
            $builder = $builder->expiresAt($expiresAt ?? $this->clock->now()->modify('+1 hour'));
        }
        if ($withIssuedAt) {
            $builder = $builder->issuedAt($issuedAt ?? $this->clock->now());
        }
        if ('' !== $nonce) {
            $builder = $builder->withClaim('nonce', $nonce ?? hash('sha256', self::NONCE));
        }
        if (null !== $authorizedParty) {
            $builder = $builder->withClaim('azp', $authorizedParty);
        }
        if (null !== $privateEmail) {
            $builder = $builder->withClaim('is_private_email', $privateEmail);
        }
        if ($withKeyId) {
            $builder = $builder->withHeader('kid', $keyId);
        }
        if (null !== $subject && '' !== $subject) {
            $builder = $builder->relatedTo($subject);
        }
        if (null !== $email && '' !== $email) {
            $builder = $builder->withClaim('email', $email);
        }
        if (null !== $emailVerified) {
            $builder = $builder->withClaim('email_verified', $emailVerified);
        }

        return $builder->getToken(new Sha256(), InMemory::plainText(($signWith ?? $this->key)->privatePem()))->toString();
    }

    /**
     * @param list<callable(): MockResponse>|null $responses
     */
    private function verifier(?array $responses = null, ?SocialProviderSettings $google = null): JwksSocialIdentityVerifier
    {
        $responses ??= [fn (): MockResponse => new MockResponse((string) json_encode(['keys' => [$this->key->jwk('key-1')]]), ['response_headers' => ['content-type' => 'application/json']])];
        $http = new MockHttpClient(function (string $method, string $url) use (&$responses): MockResponse {
            $this->requests[] = "$method $url";
            $next = array_shift($responses) ?? throw new LogicException('Unexpected HTTP request: '.$url);
            if ([] === $responses) {
                $responses[] = $next;
            }

            return $next();
        });

        return new JwksSocialIdentityVerifier(
            ['google' => $google ?? new SocialProviderSettings('https://keys.test/google', ['https://accounts.google.com', 'accounts.google.com'], [self::AUDIENCE])],
            $http,
            new ArrayAdapter(),
            $this->clock,
        );
    }

    private function assertRejected(JwksSocialIdentityVerifier $verifier, string $token): void
    {
        try {
            $verifier->verify(SocialProvider::Google, $token, self::NONCE);
            self::fail('The token was accepted.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('social-token-invalid', $problem->problemSlug());
        }
    }

    public function testAValidTokenYieldsTheVerifiedIdentity(): void
    {
        $identity = $this->verifier()->verify(SocialProvider::Google, $this->token(), self::NONCE);

        self::assertSame(SocialProvider::Google, $identity->identity->provider);
        self::assertSame('google-subject-1', $identity->identity->subject->toString());
        self::assertSame('ana@example.com', $identity->email->toString());
        self::assertTrue($identity->emailVerified);
    }

    public function testTheKeysAreFetchedOnceAndCached(): void
    {
        $verifier = $this->verifier();

        $verifier->verify(SocialProvider::Google, $this->token(), self::NONCE);
        $verifier->verify(SocialProvider::Google, $this->token(), self::NONCE);

        self::assertSame(['GET https://keys.test/google'], $this->requests);
    }

    public function testEmailVerifiedAsAStringIsUnderstood(): void
    {
        self::assertTrue($this->verifier()->verify(SocialProvider::Google, $this->token(emailVerified: 'true'), self::NONCE)->emailVerified);
        self::assertFalse($this->verifier()->verify(SocialProvider::Google, $this->token(emailVerified: 'false'), self::NONCE)->emailVerified);
        self::assertFalse($this->verifier()->verify(SocialProvider::Google, $this->token(emailVerified: false), self::NONCE)->emailVerified);
    }

    public function testAMissingEmailVerifiedClaimMeansNotVerified(): void
    {
        self::assertFalse($this->verifier()->verify(SocialProvider::Google, $this->token(emailVerified: null), self::NONCE)->emailVerified);
    }

    public function testBothGoogleIssuersAreAccepted(): void
    {
        $identity = $this->verifier()->verify(SocialProvider::Google, $this->token(issuer: 'accounts.google.com'), self::NONCE);

        self::assertSame('google-subject-1', $identity->identity->subject->toString());
    }

    public function testAnyOfSeveralAudiencesMayMatch(): void
    {
        $identity = $this->verifier()->verify(SocialProvider::Google, $this->token(audience: ['other-client', self::AUDIENCE], authorizedParty: self::AUDIENCE), self::NONCE);

        self::assertSame('google-subject-1', $identity->identity->subject->toString());
    }

    public function testATokenWithoutTheExpectedNonceIsRejected(): void
    {
        $this->assertRejected($this->verifier(), $this->token(nonce: ''));
        $this->assertRejected($this->verifier(), $this->token(nonce: hash('sha256', 'another-nonce-0123456789')));
    }

    public function testTheRawNonceInTheTokenIsNotAccepted(): void
    {
        $this->assertRejected($this->verifier(), $this->token(nonce: self::NONCE));
    }

    public function testAnEmptyNonceFromTheClientIsRejected(): void
    {
        $this->expectException(AuthenticationProblem::class);

        $this->verifier()->verify(SocialProvider::Google, $this->token(), '');
    }

    public function testTheNonceDigestMayBeUpperCase(): void
    {
        $identity = $this->verifier()->verify(SocialProvider::Google, $this->token(nonce: strtoupper(hash('sha256', self::NONCE))), self::NONCE);

        self::assertSame('google-subject-1', $identity->identity->subject->toString());
    }

    public function testATokenWithoutExpiryOrIssueTimeIsRejected(): void
    {
        $this->assertRejected($this->verifier(), $this->token(withExpiry: false));
        $this->assertRejected($this->verifier(), $this->token(withIssuedAt: false));
    }

    public function testATokenIssuedInTheFutureIsRejectedBeyondTheSkew(): void
    {
        $this->assertRejected($this->verifier(), $this->token(issuedAt: $this->clock->now()->modify('+5 minutes')));

        $identity = $this->verifier()->verify(SocialProvider::Google, $this->token(issuedAt: $this->clock->now()->modify('+30 seconds')), self::NONCE);
        self::assertSame('google-subject-1', $identity->identity->subject->toString());
    }

    public function testTheAuthorizedPartyMustBeOneOfTheAppsClientIds(): void
    {
        $this->assertRejected($this->verifier(), $this->token(authorizedParty: 'someone-elses-client'));

        $identity = $this->verifier()->verify(SocialProvider::Google, $this->token(authorizedParty: self::AUDIENCE), self::NONCE);
        self::assertSame('google-subject-1', $identity->identity->subject->toString());
    }

    public function testSeveralAudiencesNeedAnAuthorizedParty(): void
    {
        $this->assertRejected($this->verifier(), $this->token(audience: ['other-client', self::AUDIENCE]));
    }

    public function testPrivateRelayEmailsAreFlagged(): void
    {
        self::assertTrue($this->verifier()->verify(SocialProvider::Google, $this->token(privateEmail: 'true'), self::NONCE)->privateRelayEmail);
        self::assertFalse($this->verifier()->verify(SocialProvider::Google, $this->token(privateEmail: 'false'), self::NONCE)->privateRelayEmail);
        self::assertFalse($this->verifier()->verify(SocialProvider::Google, $this->token(), self::NONCE)->privateRelayEmail);
    }

    public function testATokenForAnotherAppIsRejected(): void
    {
        $this->assertRejected($this->verifier(), $this->token(audience: 'someone-elses-client'));
    }

    public function testATokenFromAnotherIssuerIsRejected(): void
    {
        $this->assertRejected($this->verifier(), $this->token(issuer: 'https://evil.example'));
    }

    public function testAnExpiredTokenIsRejected(): void
    {
        $this->assertRejected($this->verifier(), $this->token(expiresAt: $this->clock->now()->modify('-1 second')));
        $this->assertRejected($this->verifier(), $this->token(expiresAt: $this->clock->now()));
    }

    public function testATokenThatExpiresInASecondIsAccepted(): void
    {
        $identity = $this->verifier()->verify(SocialProvider::Google, $this->token(expiresAt: $this->clock->now()->modify('+1 second')), self::NONCE);

        self::assertSame('google-subject-1', $identity->identity->subject->toString());
    }

    public function testATokenSignedWithAnotherKeyIsRejected(): void
    {
        $this->assertRejected($this->verifier(), $this->token(signWith: RsaKey::generate()));
    }

    public function testAMissingSubjectOrEmailIsRejected(): void
    {
        $this->assertRejected($this->verifier(), $this->token(subject: null));
        $this->assertRejected($this->verifier(), $this->token(email: null));
        $this->assertRejected($this->verifier(), $this->token(email: 'not an email'));
    }

    public function testGarbageIsRejected(): void
    {
        foreach (['', 'hello', 'a.b.c', 'bm90.bm90.bm90'] as $garbage) {
            $this->assertRejected($this->verifier(), $garbage);
        }
    }

    public function testATokenWithoutAKeyIdIsRejected(): void
    {
        $this->assertRejected($this->verifier(), $this->token(withKeyId: false));
    }

    public function testAnUnknownKeyIdTriggersOneRefreshAndThenSucceedsWhenTheProviderRotatedItsKeys(): void
    {
        $oldKey = RsaKey::generate();
        $verifier = $this->verifier([
            static fn (): MockResponse => new MockResponse((string) json_encode(['keys' => [$oldKey->jwk('old')]])),
            fn (): MockResponse => new MockResponse((string) json_encode(['keys' => [$oldKey->jwk('old'), $this->key->jwk('key-1')]])),
        ]);

        $identity = $verifier->verify(SocialProvider::Google, $this->token(), self::NONCE);

        self::assertSame('google-subject-1', $identity->identity->subject->toString());
        self::assertCount(2, $this->requests);
    }

    public function testAnUnknownKeyIdThatStaysUnknownIsRejectedAfterTheRefresh(): void
    {
        $this->assertRejected($this->verifier(), $this->token(keyId: 'never-published'));
        self::assertCount(2, $this->requests);
    }

    public function testAnUnreachableProviderIsReportedAsUnavailableNotAsAnInvalidToken(): void
    {
        $verifier = $this->verifier([static fn (): MockResponse => new MockResponse('', ['http_code' => 500])]);

        $this->expectException(SocialProviderUnavailable::class);

        $verifier->verify(SocialProvider::Google, $this->token(), self::NONCE);
    }

    public function testATimeoutIsReportedAsUnavailable(): void
    {
        $verifier = $this->verifier([static fn (): MockResponse => new MockResponse('', ['error' => 'timeout'])]);

        $this->expectException(SocialProviderUnavailable::class);

        $verifier->verify(SocialProvider::Google, $this->token(), self::NONCE);
    }

    public function testMalformedKeySetsAreReportedAsUnavailable(): void
    {
        foreach (['not json', '{"keys": "nope"}', '{"nokeys": []}', '{"keys": []}', '{"keys": [{"kty": "EC", "kid": "x"}]}', '{"keys": [{"kty": "RSA", "kid": "x", "n": "!!!", "e": "AQAB"}]}'] as $body) {
            $verifier = $this->verifier([static fn (): MockResponse => new MockResponse($body)]);
            try {
                $verifier->verify(SocialProvider::Google, $this->token(), self::NONCE);
                self::fail("Accepted key set: $body");
            } catch (SocialProviderUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAProviderThatIsNotConfiguredIsRejected(): void
    {
        $this->expectException(AuthenticationProblem::class);

        $this->verifier()->verify(SocialProvider::Apple, $this->token(), self::NONCE);
    }

    public function testProviderSettingsKeepTheirValues(): void
    {
        $settings = new SocialProviderSettings('https://keys.test', ['iss'], ['aud']);

        self::assertSame('https://keys.test', $settings->jwksUrl);
        self::assertSame(['iss'], $settings->issuers);
        self::assertSame(['aud'], $settings->audiences);
    }

    public function testAJwkIsConvertedToTheSamePemOpenSslProduces(): void
    {
        $jwk = $this->key->jwk('k');

        $pem = RsaJwk::toPem($jwk['n'], $jwk['e']);

        self::assertSame($this->key->publicPem(), $pem);
    }

    public function testAnotherKeyIsConvertedTheSameWay(): void
    {
        foreach ([1, 2, 3] as $ignored) {
            $key = RsaKey::generate();
            $jwk = $key->jwk('k');

            self::assertSame($key->publicPem(), RsaJwk::toPem($jwk['n'], $jwk['e']), 'moduli with a leading zero byte need the extra padding byte');
        }
    }

    public function testMalformedJwkValuesAreRejected(): void
    {
        foreach ([['!!!', 'AQAB'], ['AQAB', '!!!'], ['', 'AQAB'], ['AQAB', '']] as [$n, $e]) {
            try {
                RsaJwk::toPem($n, $e);
                self::fail('A malformed key was converted.');
            } catch (RuntimeException $failure) {
                self::assertStringContainsString('malformed', $failure->getMessage());
            }
        }
    }
}
