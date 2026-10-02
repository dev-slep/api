<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Infrastructure\Security\JwtAuthenticator;
use App\Authentication\Infrastructure\Security\ProblemEntryPoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\Authentication\TotpCodes;
use App\Tests\Support\Fixtures\Http\ProtectedFixtureController;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The firewall and the access rules, tested on the fixture routes under the protected prefix.
 */
#[CoversClass(JwtAuthenticator::class)]
#[CoversClass(ProblemEntryPoint::class)]
#[CoversClass(ProtectedFixtureController::class)]
final class AccessControlTest extends AuthenticationIntegrationTestCase
{
    private const string AUTHENTICATED = '/api/v1/_test/authenticated';
    private const string ADMIN = '/api/v1/admin/_test/admin';

    public function testProtectedRoutesRefuseAnonymousRequestsWith401AndAChallenge(): void
    {
        foreach ([self::AUTHENTICATED, self::ADMIN] as $path) {
            $response = $this->call('GET', $path);

            self::assertSame(401, $response->getStatusCode(), $path);
            self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
            self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
            self::assertSame('https://slep.example/problems/unauthorized', $this->json()['type']);
            self::assertSame($path, $this->json()['instance']);
        }
    }

    public function testAnyUserReachesTheAuthenticatedRouteButOnlyAnAdminTheAdminRoute(): void
    {
        $driver = $this->login($this->createAccount()->email()->toString());

        self::assertSame(200, $this->call('GET', self::AUTHENTICATED, headers: $this->bearer($driver['accessToken']))->getStatusCode());
        $forbidden = $this->call('GET', self::ADMIN, headers: $this->bearer($driver['accessToken']));
        self::assertSame(403, $forbidden->getStatusCode());
        self::assertSame('https://slep.example/problems/forbidden', $this->json()['type']);
    }

    public function testAnAdminNeedsTheSecondFactorToReachTheAdminRoute(): void
    {
        $clock = $this->freezeClock('2026-01-01T12:00:10+00:00');
        $admin = $this->createAdminWithSecondFactor();
        $clock->advance('+30 seconds');
        $pending = $this->jsonString('accessToken', $this->call('POST', '/api/v1/auth/login', ['email' => $admin['email'], 'password' => self::PASSWORD]));

        self::assertSame(403, $this->call('GET', self::ADMIN, headers: $this->bearer($pending))->getStatusCode(), 'the password alone is not enough');
        self::assertSame(200, $this->call('GET', self::AUTHENTICATED, headers: $this->bearer($pending))->getStatusCode(), 'the pending token is a valid token, it just has no roles');

        $code = TotpCodes::at($admin['secret'], $clock->now()->getTimestamp());
        $full = $this->jsonString('accessToken', $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $code], $this->bearer($pending)));
        self::assertSame(200, $this->call('GET', self::ADMIN, headers: $this->bearer($full))->getStatusCode());
    }

    public function testBadTokensAreRefusedWithTheSpecificProblem(): void
    {
        $tokens = $this->login($this->createAccount()->email()->toString());
        [$header, $payload, $signature] = explode('.', $tokens['accessToken']);
        $tampered = $header.'.'.rtrim(strtr(base64_encode((string) json_encode(['sub' => 'x', 'roles' => ['ROLE_ADMIN']])), '+/', '-_'), '=').'.'.$signature;

        $cases = [
            'garbage' => ['garbage', 'token-invalid'],
            'tampered payload' => [$tampered, 'token-invalid'],
            'signature cut off' => [$header.'.'.$payload.'.', 'token-invalid'],
        ];
        foreach ($cases as $name => [$token, $slug]) {
            $response = $this->call('GET', self::AUTHENTICATED, headers: $this->bearer($token));
            self::assertSame(401, $response->getStatusCode(), $name);
            self::assertSame("https://slep.example/problems/$slug", $this->json()['type'], $name);
        }
    }

    public function testATokenExpiresExactlyAtItsExpiryTime(): void
    {
        $clock = $this->freezeClock();
        $tokens = $this->login($this->createAccount()->email()->toString());

        $clock->advance('+899 seconds');
        self::assertSame(200, $this->call('GET', self::AUTHENTICATED, headers: $this->bearer($tokens['accessToken']))->getStatusCode());

        $clock->advance('+1 second');
        $expired = $this->call('GET', self::AUTHENTICATED, headers: $this->bearer($tokens['accessToken']));
        self::assertSame(401, $expired->getStatusCode());
        self::assertSame('https://slep.example/problems/token-expired', $this->json()['type']);
    }

    public function testOtherAuthorizationSchemesAreRefused(): void
    {
        $response = $this->call('GET', self::AUTHENTICATED, headers: ['Authorization' => 'Basic dXNlcjpwYXNz']);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/token-invalid', $this->json()['type']);
    }

    public function testPublicRoutesIgnoreAnInvalidTokenOnlyWhenNoneIsSent(): void
    {
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $this->createAccount()->email()->toString(), 'password' => self::PASSWORD])->getStatusCode());
        self::assertSame(401, $this->call('POST', '/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x'], $this->bearer('garbage'))->getStatusCode(), 'a bad token is never silently ignored');
    }

    public function testHealthEndpointsStayPublic(): void
    {
        $this->client->request('GET', '/health/live');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testMethodsAreEnforcedBeforeAccess(): void
    {
        self::assertSame(405, $this->call('POST', self::AUTHENTICATED)->getStatusCode());
    }
}
