<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\OpenApi\OpenApiDocument;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class OpenApiTest extends IntegrationTestCase
{
    private const string FIXTURE = __DIR__.'/../../Support/OpenApi/Fixtures/openapi.yaml';

    public function testDumpedSpecIsAValidOpenApi31Document(): void
    {
        $document = OpenApiDocument::fromYaml($this->dump());

        self::assertSame([], $document->violations());
        self::assertSame('3.1.0', ((array) $document->spec)['openapi'] ?? null);
    }

    public function testCommittedSpecIsUpToDate(): void
    {
        $committed = file_get_contents(__DIR__.'/../../../openapi/openapi.yaml');

        self::assertSame($this->dump(), $committed, 'openapi/openapi.yaml is stale: run `make openapi` and commit the result');
    }

    public function testSchemaAssertionAcceptsAConformingResponse(): void
    {
        $response = new JsonResponse(['id' => '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b', 'price' => ['amount' => 100, 'currency' => 'RSD']]);

        self::assertMatchesOpenApiSchema($response, 'GET', '/api/v1/things/0190a1b2', OpenApiDocument::fromYamlFile(self::FIXTURE));
    }

    public function testSchemaAssertionFailsOnAResponseThatBreaksTheSchema(): void
    {
        $response = new JsonResponse(['id' => '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b', 'price' => ['amount' => '100', 'currency' => 'rsd']]);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessage('does not match the OpenAPI document');

        self::assertMatchesOpenApiSchema($response, 'GET', '/api/v1/things/0190a1b2', OpenApiDocument::fromYamlFile(self::FIXTURE));
    }

    public function testSchemaAssertionFailsOnAnUndocumentedEndpoint(): void
    {
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        self::assertMatchesOpenApiSchema(new Response('{}', 200, ['Content-Type' => 'application/json']), 'GET', '/api/v1/unknown', OpenApiDocument::fromYamlFile(self::FIXTURE));
    }

    private function dump(): string
    {
        $application = new Application(static::bootKernel());
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $application->run(new ArrayInput(['command' => 'nelmio:apidoc:dump', '--format' => 'yaml', '-v' => true]), $output);

        return $output->fetch();
    }
}
