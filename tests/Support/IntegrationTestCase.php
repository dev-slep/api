<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Tests\Support\OpenApi\OpenApiDocument;
use App\Tests\Support\OpenApi\OpenApiResponseValidator;
use App\Tests\Support\WireMock\WireMockClient;

use function dirname;

use const JSON_THROW_ON_ERROR;

use function sprintf;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Base class for integration tests: everything is real (Postgres+PostGIS, Messenger, MinIO, Mailpit);
 * third parties are reached through WireMock, which is reset before every test.
 *
 * Every test runs in a transaction that DAMA rolls back. Tests that must commit
 * (worker consumption) use `#[SkipDatabaseRollback]` and the {@see TruncatesSchemas} trait.
 */
abstract class IntegrationTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->wireMock()->reset();
    }

    protected function wireMock(): WireMockClient
    {
        $http = static::getContainer()->get(HttpClientInterface::class);

        return new WireMockClient($http, self::env('WIREMOCK_URL'));
    }

    /**
     * @param array<mixed>          $body
     * @param array<string, string> $headers
     */
    protected function jsonRequest(string $method, string $uri, array $body = [], array $headers = []): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: [] === $body ? null : json_encode($body, JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }

    /**
     * Runs a worker on the given transport in-process for a few seconds (long enough for retries),
     * the way the supervisord worker would, and returns its output.
     */
    protected function consumeMessages(string $transport = 'async', int $seconds = 4): string
    {
        $application = new Application(static::bootKernel());
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $application->run(
            new ArrayInput(['command' => 'messenger:consume', 'receivers' => [$transport], '--time-limit' => $seconds, '--sleep' => 0.2]),
            $output,
        );

        return $output->fetch();
    }

    /**
     * Asserts that the response matches what the OpenAPI document (openapi/openapi.yaml by default)
     * declares for this operation: documented status and content type, and a body that satisfies the JSON Schema.
     */
    protected static function assertMatchesOpenApiSchema(Response $response, string $method, string $path, ?OpenApiDocument $document = null): void
    {
        $document ??= OpenApiDocument::fromYamlFile(dirname(__DIR__, 2).'/openapi/openapi.yaml');

        $violations = (new OpenApiResponseValidator($document))->violations(
            $method,
            $path,
            $response->getStatusCode(),
            $response->headers->get('Content-Type'),
            (string) $response->getContent(),
        );

        self::assertSame([], $violations, sprintf("%s %s does not match the OpenAPI document:\n- %s", strtoupper($method), $path, implode("\n- ", $violations)));
    }

    protected static function env(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        self::assertIsString($value, "Environment variable $name is not set");

        return $value;
    }
}
