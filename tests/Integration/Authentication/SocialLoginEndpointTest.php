<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Contract\Event\UserLoggedInV1;
use App\Authentication\Contract\Event\UserRegisteredV1;
use App\Authentication\Infrastructure\Http\Controller\SocialLoginController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\Authentication\SocialTokens;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SocialLoginController::class)]
#[CoversEndpoint('POST', '/api/v1/auth/social/{provider}')]
final class SocialLoginEndpointTest extends AuthenticationIntegrationTestCase
{
    private SocialTokens $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokens = new SocialTokens($this->freezeClock());
        // Signing keys are cached for an hour: start every test from an empty cache
        $cache = static::getContainer()->get('cache.app');
        $cache->clear();
    }

    public function testAFirstSignInRegistersAVerifiedAccountAndLogsIn(): void
    {
        $email = $this->uniqueEmail('soc');

        $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($email, 'g-'.$email), 'role' => 'TOWER', 'phone' => '+381641234567']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/social/google');
        self::assertArrayHasKey('refreshToken', $this->json());
        $row = $this->accountRow($email);
        self::assertNotNull($row);
        self::assertSame('TOWER', $row['role']);
        self::assertNotNull($row['email_verified_at']);
        self::assertNull($row['password_hash']);
        self::assertSame(1, $this->countRows('authentication.social_identity', "provider = 'google' AND account_id = :id", ['id' => $row['id']]));
        $this->assertEventStored(UserRegisteredV1::class);
        $this->assertEventStored(UserLoggedInV1::class);
        self::assertSame([], $this->mailsTo($email), 'a provider-confirmed email needs no verification mail');
    }

    public function testASecondSignInFindsTheSameAccount(): void
    {
        $email = $this->uniqueEmail('soc');
        $token = $this->tokens->apple($email, 'a-'.$email);
        $this->call('POST', '/api/v1/auth/social/apple', ['nonce' => SocialTokens::NONCE, 'idToken' => $token, 'role' => 'DRIVER']);

        $response = $this->call('POST', '/api/v1/auth/social/apple', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->apple($email, 'a-'.$email)]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $this->countRows('authentication.user_account', 'email = :email', ['email' => $email]));
    }

    public function testAnExistingAccountWithTheSameConfirmedEmailGetsTheIdentityLinked(): void
    {
        $account = $this->createAccount();
        $email = $account->email()->toString();

        $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($email, 'g-link')]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, $this->countRows('authentication.social_identity', 'account_id = :id', ['id' => $account->id()->toString()]));
        self::assertSame(1, $this->countRows('authentication.user_account', 'email = :email', ['email' => $email]));
    }

    public function testASocialSignInDisablesThePasswordOfAnAccountRegisteredWithoutConfirmingTheEmail(): void
    {
        $account = $this->createAccount(verified: false, password: 'attacker password');
        $email = $account->email()->toString();

        $linked = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($email, 'g-owner')]);
        $attacker = $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => 'attacker password']);

        self::assertSame(200, $linked->getStatusCode(), (string) $linked->getContent());
        self::assertSame(401, $attacker->getStatusCode(), 'the password chosen before the owner proved the address no longer works');
        self::assertSame(1, $this->countRows('authentication.user_account', 'email = :email AND password_hash IS NULL', ['email' => $email]));
    }

    public function testAnEmailTheProviderDoesNotConfirmNeverTakesOverAnAccount(): void
    {
        $account = $this->createAccount();

        $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($account->email()->toString(), 'g-evil', emailVerified: false)]);

        self::assertSame(409, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/social/google');
        self::assertSame(0, $this->countRows('authentication.social_identity'));
    }

    public function testAnAccountWhoseEmailIsNotConfirmedMustVerifyFirst(): void
    {
        $email = $this->uniqueEmail('soc');

        $response = $this->call('POST', '/api/v1/auth/social/apple', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->apple($email, 'a-unverified', emailVerified: false), 'role' => 'DRIVER', 'locale' => 'en']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/email-not-verified', $this->json()['type']);
        self::assertNull($this->accountColumn($email, 'email_verified_at'));
        self::assertCount(1, $this->mailsTo($email));
    }

    public function testAFirstSignInNeedsARole(): void
    {
        $email = $this->uniqueEmail('soc');

        $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($email, 'g-norole')]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/social-role-required', $this->json()['type']);
        self::assertNull($this->accountRow($email));
    }

    public function testAdminsCannotSignInSociallyEvenWithTheirEmail(): void
    {
        $admin = $this->createAdminWithSecondFactor();

        $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($admin['email'], 'g-admin')]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, $this->countRows('authentication.social_identity'));
    }

    public function testForgedExpiredAndForeignTokensAreRefused(): void
    {
        $email = $this->uniqueEmail('soc');
        $cases = [
            'forged signature' => $this->tokens->google($email, 'g-1', signWith: 'other'),
            'wrong audience' => $this->tokens->google($email, 'g-2', audience: 'someone-elses-app'),
            'wrong issuer' => $this->tokens->google($email, 'g-3', issuer: 'https://evil.example'),
            'expired' => $this->tokens->google($email, 'g-4', expiresIn: '-1 second'),
            'garbage' => 'not.a.token',
            'unknown key id' => $this->tokens->google($email, 'g-5', keyId: 'rotated-away'),
        ];

        foreach ($cases as $name => $token) {
            $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $token, 'role' => 'DRIVER']);
            self::assertSame(401, $response->getStatusCode(), $name);
            self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/social/google');
            self::assertSame('https://slep.example/problems/social-token-invalid', $this->json()['type'], $name);
        }
        self::assertNull($this->accountRow($email));
    }

    public function testATokenThatExpiresInASecondIsAccepted(): void
    {
        $email = $this->uniqueEmail('soc');

        $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($email, 'g-edge', expiresIn: '+1 second'), 'role' => 'DRIVER']);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testTheProvidersKeysAreFetchedOnceAndCached(): void
    {
        $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($this->uniqueEmail('soc'), 'g-c1'), 'role' => 'DRIVER']);
        $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($this->uniqueEmail('soc'), 'g-c2'), 'role' => 'DRIVER']);

        self::assertTrue($this->wireMock()->verify(['method' => 'GET', 'urlPath' => '/google/jwks'], 1));
    }

    public function testAnUnreachableProviderAnswers503NotAnInvalidToken(): void
    {
        $this->wireMock()->stubFor(['priority' => 1, 'request' => ['method' => 'GET', 'urlPath' => '/google/jwks'], 'response' => ['status' => 500, 'body' => 'down']]);

        $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($this->uniqueEmail('soc'), 'g-down'), 'role' => 'DRIVER']);

        self::assertSame(503, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/social/google');
        self::assertSame('https://slep.example/problems/social-provider-unavailable', $this->json()['type']);
    }

    public function testATimeoutAndMalformedKeySetsAnswer503AndLeaveNoAccount(): void
    {
        $email = $this->uniqueEmail('soc');
        foreach ([['fixedDelayMilliseconds' => 7000, 'status' => 200, 'body' => '{}'], ['status' => 200, 'body' => 'not json'], ['status' => 200, 'body' => '{"keys": []}']] as $responseStub) {
            $this->wireMock()->reset();
            $this->wireMock()->stubFor(['priority' => 1, 'request' => ['method' => 'GET', 'urlPath' => '/google/jwks'], 'response' => $responseStub]);
            static::getContainer()->get('cache.app')->clear();

            $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($email, 'g-bad'), 'role' => 'DRIVER']);

            self::assertSame(503, $response->getStatusCode(), json_encode($responseStub, JSON_THROW_ON_ERROR));
        }
        self::assertNull($this->accountRow($email));
    }

    public function testARotatedKeyIsPickedUpAfterOneRefresh(): void
    {
        $email = $this->uniqueEmail('soc');
        // The cache holds the old key set; the token is signed with a key the provider published since
        $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($this->uniqueEmail('soc'), 'g-warm'), 'role' => 'DRIVER']);
        $this->wireMock()->stubFor(['priority' => 1, 'request' => ['method' => 'GET', 'urlPath' => '/google/jwks'], 'response' => ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($this->tokens->jwksWithAdditionalKey('rotated-in'), JSON_THROW_ON_ERROR)]]);

        $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => $this->tokens->google($email, 'g-rotated', keyId: 'rotated-in', signWith: 'rotated'), 'role' => 'DRIVER']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testUnknownProvidersAreNotFoundAndBodiesAreValidated(): void
    {
        self::assertSame(404, $this->call('POST', '/api/v1/auth/social/facebook', ['nonce' => SocialTokens::NONCE, 'idToken' => 'x'])->getStatusCode());
        foreach ([[], ['nonce' => SocialTokens::NONCE, 'idToken' => ''], ['nonce' => SocialTokens::NONCE, 'idToken' => str_repeat('a', 8193)], ['nonce' => SocialTokens::NONCE, 'idToken' => 'x', 'role' => 'ADMIN'], ['nonce' => SocialTokens::NONCE, 'idToken' => 'x', 'phone' => str_repeat('1', 33)], ['nonce' => SocialTokens::NONCE, 'idToken' => 'x', 'locale' => 'EN']] as $body) {
            $response = $this->call('POST', '/api/v1/auth/social/google', $body);
            self::assertSame(422, $response->getStatusCode(), json_encode($body, JSON_THROW_ON_ERROR));
            self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/social/google');
        }
    }

    public function testSignInsAreRateLimitedPerAddress(): void
    {
        $limited = null;
        for ($i = 0; $i < 105 && null === $limited; ++$i) {
            $response = $this->call('POST', '/api/v1/auth/social/google', ['nonce' => SocialTokens::NONCE, 'idToken' => 'garbage']);
            $limited = 429 === $response->getStatusCode() ? $response : null;
        }

        self::assertNotNull($limited);
        self::assertMatchesOpenApiSchema($limited, 'POST', '/api/v1/auth/social/google');
    }
}
