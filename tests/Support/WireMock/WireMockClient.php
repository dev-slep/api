<?php

declare(strict_types=1);

namespace App\Tests\Support\WireMock;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client for WireMock's admin API (stubs, request verification, reset).
 */
final readonly class WireMockClient
{
    public function __construct(
        private HttpClientInterface $http,
        private string $baseUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $mapping a WireMock stub mapping ({"request": ..., "response": ...})
     */
    public function stubFor(array $mapping): void
    {
        $this->http->request('POST', $this->baseUrl.'/__admin/mappings', ['json' => $mapping])->getContent();
    }

    /**
     * @param array<string, mixed> $requestPattern e.g. {"method": "POST", "url": "/fcm/send"}
     */
    public function verify(array $requestPattern, int $expectedCount): bool
    {
        $response = $this->http->request('POST', $this->baseUrl.'/__admin/requests/count', ['json' => $requestPattern]);

        return $expectedCount === ($response->toArray()['count'] ?? null);
    }

    /**
     * @param array<string, mixed> $requestPattern
     *
     * @return list<array<string, mixed>> the matching received requests
     */
    public function findRequests(array $requestPattern): array
    {
        $response = $this->http->request('POST', $this->baseUrl.'/__admin/requests/find', ['json' => $requestPattern]);

        /** @var list<array<string, mixed>> $requests */
        $requests = $response->toArray()['requests'] ?? [];

        return $requests;
    }

    /** Removes all stubs and the request journal. */
    public function reset(): void
    {
        $this->http->request('POST', $this->baseUrl.'/__admin/reset')->getContent();
    }
}
