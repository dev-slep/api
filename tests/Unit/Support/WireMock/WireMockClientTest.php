<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\WireMock;

use App\Tests\Support\WireMock\WireMockClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(WireMockClient::class)]
final class WireMockClientTest extends TestCase
{
    public function testStubForPostsTheMapping(): void
    {
        /** @var list<mixed> $captured */
        $captured = [];
        $client = $this->client(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, $options['body'] ?? null];

            return new MockResponse('{}', ['http_code' => 201]);
        });

        $client->stubFor(['request' => ['url' => '/x']]);

        self::assertSame('POST', $captured[0]);
        self::assertSame('http://wiremock/__admin/mappings', $captured[1]);
        self::assertSame('{"request":{"url":"\/x"}}', $captured[2]);
    }

    public function testVerifyComparesTheCount(): void
    {
        $client = $this->client(static fn (): MockResponse => new MockResponse('{"count": 2}'));

        self::assertTrue($client->verify(['url' => '/x'], 2));
        self::assertFalse($client->verify(['url' => '/x'], 3));
    }

    public function testFindRequestsReturnsTheRequests(): void
    {
        $client = $this->client(static fn (): MockResponse => new MockResponse('{"requests": [{"url": "/x"}]}'));

        self::assertSame([['url' => '/x']], $client->findRequests(['url' => '/x']));
    }

    public function testFindRequestsReturnsEmptyListWhenNothingMatches(): void
    {
        $client = $this->client(static fn (): MockResponse => new MockResponse('{}'));

        self::assertSame([], $client->findRequests(['url' => '/x']));
    }

    public function testResetCallsTheResetEndpoint(): void
    {
        $url = null;
        $client = $this->client(static function (string $method, string $u) use (&$url): MockResponse {
            $url = $method.' '.$u;

            return new MockResponse('');
        });

        $client->reset();

        self::assertSame('POST http://wiremock/__admin/reset', $url);
    }

    /**
     * @param callable(string, string, array<string, mixed>): MockResponse $responder
     */
    private function client(callable $responder): WireMockClient
    {
        return new WireMockClient(new MockHttpClient($responder), 'http://wiremock');
    }
}
