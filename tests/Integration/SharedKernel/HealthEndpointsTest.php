<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\SharedKernel\Infrastructure\Http\Controller\HealthController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Fixtures\Health\ToggleableHealthCheck;
use App\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HealthController::class)]
#[CoversEndpoint('GET', '/health/live')]
#[CoversEndpoint('GET', '/health/ready')]
final class HealthEndpointsTest extends IntegrationTestCase
{
    public function testLiveIsAlwaysOk(): void
    {
        $this->client->request('GET', '/health/live');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('{"status":"ok"}', $this->client->getResponse()->getContent());
    }

    public function testReadyReportsEveryCheckWhenAllServicesAreUp(): void
    {
        $this->client->request('GET', '/health/ready');
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame(
            '{"status":"ok","checks":{"database":{"status":"ok"},"messenger":{"status":"ok"},"fixture":{"status":"ok"}}}',
            $response->getContent(),
        );
    }

    public function testReadyIs503WhenACheckFails(): void
    {
        $this->client->disableReboot();
        static::getContainer()->get(ToggleableHealthCheck::class)->failing = true;

        $this->client->request('GET', '/health/ready');
        $response = $this->client->getResponse();

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(
            '{"status":"fail","checks":{"database":{"status":"ok"},"messenger":{"status":"ok"},"fixture":{"status":"fail"}}}',
            $response->getContent(),
        );
    }

    public function testHealthEndpointsAreNotSubjectToTheApiRules(): void
    {
        $this->client->request('GET', '/health/live', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertNotSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testOtherMethodsAreNotAllowed(): void
    {
        foreach (['/health/live', '/health/ready'] as $path) {
            $this->client->request('POST', $path);

            self::assertSame(405, $this->client->getResponse()->getStatusCode(), $path);
            self::assertStringContainsString('GET', (string) $this->client->getResponse()->headers->get('Allow'));
        }
    }
}
