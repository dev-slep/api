<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\OpenApi;

use App\Tests\Support\OpenApi\OpenApiDocument;
use App\Tests\Support\OpenApi\OpenApiResponseValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenApiDocument::class)]
#[CoversClass(OpenApiResponseValidator::class)]
final class OpenApiTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../../Support/OpenApi/Fixtures/openapi.yaml';
    private const string THING = '{"id": "0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b", "price": {"amount": 100, "currency": "RSD"}, "note": null}';

    public function testFixtureIsAValidOpenApi31Document(): void
    {
        self::assertSame([], OpenApiDocument::fromYamlFile(self::FIXTURE)->violations());
    }

    public function testBrokenDocumentIsReported(): void
    {
        $document = OpenApiDocument::fromYaml("openapi: 3.0.3\ninfo:\n  title: x\npaths: {}\n");

        self::assertNotSame([], $document->violations(), 'wrong version and missing info.version');
    }

    public function testNonDocumentYamlIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OpenApiDocument::fromYaml('just a string');
    }

    public function testMatchingResponseHasNoViolations(): void
    {
        self::assertSame([], $this->violations('GET', '/api/v1/things/0190a1b2', 200, 'application/json', self::THING));
    }

    public function testContentTypeParametersAreIgnored(): void
    {
        self::assertSame([], $this->violations('GET', '/api/v1/things/abc', 200, 'application/json; charset=utf-8', self::THING));
    }

    public function testSchemaViolationsAreReported(): void
    {
        $violations = $this->violations('GET', '/api/v1/things/abc', 200, 'application/json', '{"id": "x", "price": {"amount": "100", "currency": "rsd"}}');

        self::assertNotSame([], $violations);
    }

    public function testMissingRequiredPropertyIsReported(): void
    {
        self::assertNotSame([], $this->violations('GET', '/api/v1/things/abc', 200, 'application/json', '{"id": "0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b"}'));
    }

    public function testAdditionalPropertiesAreEnforcedWhereForbidden(): void
    {
        $body = '{"id": "0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b", "price": {"amount": 1, "currency": "RSD", "extra": true}}';

        self::assertNotSame([], $this->violations('GET', '/api/v1/things/abc', 200, 'application/json', $body));
    }

    public function testNullableTypeFromJsonSchema2020IsAccepted(): void
    {
        $body = '{"id": "0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b", "price": {"amount": 1, "currency": "RSD"}, "note": "hi"}';

        self::assertSame([], $this->violations('GET', '/api/v1/things/abc', 200, 'application/json', $body));
    }

    public function testProblemResponsesAreValidatedAgainstTheirOwnSchema(): void
    {
        self::assertSame([], $this->violations('GET', '/api/v1/things/abc', 404, 'application/problem+json', '{"type": "t", "title": "Not found", "status": 404}'));
        self::assertNotSame([], $this->violations('GET', '/api/v1/things/abc', 404, 'application/problem+json', '{"title": "Not found"}'));
    }

    public function testUndocumentedPathMethodStatusAndContentTypeAreReported(): void
    {
        self::assertSame(['Path "/api/v1/other" is not documented.'], $this->violations('GET', '/api/v1/other', 200, 'application/json', '{}'));
        self::assertSame(['DELETE /api/v1/things/{id} is not documented.'], $this->violations('DELETE', '/api/v1/things/abc', 200, 'application/json', '{}'));
        self::assertSame(['GET /api/v1/things/{id} has no documented 500 response.'], $this->violations('GET', '/api/v1/things/abc', 500, 'application/json', '{}'));
        self::assertCount(1, $this->violations('GET', '/api/v1/things/abc', 200, 'text/html', '<p>'));
    }

    public function testDefaultResponseCoversUnlistedStatuses(): void
    {
        self::assertSame([], $this->violations('POST', '/api/v1/things', 201, 'application/json', '{}'));
        self::assertNotSame([], $this->violations('POST', '/api/v1/things', 201, 'application/json', '[]'));
    }

    public function testResponsesWithoutContentMustHaveNoBody(): void
    {
        self::assertSame([], $this->violations('GET', '/api/v1/things/abc', 204, null, ''));
        self::assertCount(1, $this->violations('GET', '/api/v1/things/abc', 204, 'application/json', '{}'));
    }

    public function testInvalidJsonBodyIsReported(): void
    {
        $violations = $this->violations('GET', '/api/v1/things/abc', 200, 'application/json', '{not json');

        self::assertStringContainsString('not valid JSON', $violations[0]);
    }

    private function validator(): OpenApiResponseValidator
    {
        return new OpenApiResponseValidator(OpenApiDocument::fromYamlFile(self::FIXTURE));
    }

    /**
     * @return list<string>
     */
    private function violations(string $method, string $path, int $status, ?string $contentType, string $body): array
    {
        return $this->validator()->violations($method, $path, $status, $contentType, $body);
    }
}
