<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The web app lives on another origin, so a browser asks first (preflight) and then needs the
 * response headers. Only the configured origins are answered, and only under /api/.
 */
#[CoversNothing]
final class CorsTest extends IntegrationTestCase
{
    private const string WEB_APP = 'http://localhost:8081';

    public function testAPreflightFromTheWebAppIsAnswered(): void
    {
        $this->client->request('OPTIONS', '/api/v1/auth/register', server: [
            'HTTP_ORIGIN' => self::WEB_APP,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,idempotency-key,accept-language',
        ]);
        $response = $this->client->getResponse();

        self::assertTrue($response->isSuccessful());
        self::assertSame(self::WEB_APP, $response->headers->get('Access-Control-Allow-Origin'));
        self::assertContains('POST', array_map('trim', explode(',', (string) $response->headers->get('Access-Control-Allow-Methods'))));
        $allowed = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        foreach (['content-type', 'idempotency-key', 'accept-language', 'authorization'] as $header) {
            self::assertStringContainsString($header, $allowed);
        }
        self::assertSame('3600', $response->headers->get('Access-Control-Max-Age'));
        self::assertNotSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function testARealCallFromTheWebAppIsAllowedAndExposesTheHeadersItReads(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/email/resend', ['email' => 'nobody@example.com'], ['Origin' => self::WEB_APP]);

        self::assertSame(self::WEB_APP, $response->headers->get('Access-Control-Allow-Origin'));
        $exposed = strtolower((string) $response->headers->get('Access-Control-Expose-Headers'));
        self::assertStringContainsString('retry-after', $exposed);
        self::assertStringContainsString('idempotency-replayed', $exposed);
    }

    public function testErrorResponsesAreReadableByTheWebAppToo(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong'], ['Origin' => self::WEB_APP]);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(self::WEB_APP, $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignOrigins(): iterable
    {
        yield 'another site' => ['https://evil.example'];
        yield 'localhost as a subdomain of another site' => ['http://localhost.evil.example'];
        yield 'localhost with a path-like suffix' => ['http://localhost:8081.evil.example'];
        yield 'the staging site before it is configured' => ['https://app.slep.example'];
    }

    #[DataProvider('foreignOrigins')]
    public function testAnOriginThatIsNotConfiguredGetsNoCorsHeaders(string $origin): void
    {
        $this->client->request('OPTIONS', '/api/v1/auth/login', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $preflight = $this->client->getResponse();
        $call = $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'a@b.co', 'password' => 'x'], ['Origin' => $origin]);

        self::assertNull($preflight->headers->get('Access-Control-Allow-Origin'));
        self::assertNull($call->headers->get('Access-Control-Allow-Origin'));
    }

    public function testNothingOutsideTheApiIsOpened(): void
    {
        $this->client->request('GET', '/health/live', server: ['HTTP_ORIGIN' => self::WEB_APP]);

        self::assertNull($this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testACallWithoutAnOriginIsUnchanged(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'a@b.co', 'password' => 'x']);

        self::assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }
}
