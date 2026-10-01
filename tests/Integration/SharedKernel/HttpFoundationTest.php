<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\ProblemJson;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class HttpFoundationTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function errors(): iterable
    {
        yield 'domain exception with problem type' => ['GET', '/api/_test/problem', 409, 'fixture-conflict'];
        yield 'plain domain exception' => ['GET', '/api/_test/domain-error', 422, 'domain-error'];
        yield 'not found exception' => ['GET', '/api/_test/not-found', 404, 'not-found'];
        yield 'unknown route' => ['GET', '/api/_test/unknown-route', 404, 'not-found'];
        yield 'access denied' => ['GET', '/api/_test/forbidden', 403, 'forbidden'];
        yield 'unauthenticated' => ['GET', '/api/_test/unauthorized', 401, 'unauthorized'];
        yield 'wrong method' => ['DELETE', '/api/_test/not-found', 405, 'method-not-allowed'];
        yield 'unexpected error' => ['GET', '/api/_test/boom', 500, 'internal-error'];
    }

    #[DataProvider('errors')]
    public function testErrorsAreRfc9457ProblemResponses(string $method, string $uri, int $status, string $slug): void
    {
        $response = $this->jsonRequest($method, $uri);
        $body = $this->problem($response);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame($status, $body->status);
        self::assertSame($slug, $body->slug());
        self::assertNotSame('', $body->title);
        self::assertSame($uri, $body->instance);
    }

    public function testValidationFailuresAreReportedPerField(): void
    {
        $response = $this->jsonRequest('POST', '/api/_test/payload', ['name' => '', 'count' => 11]);
        $body = $this->problem($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('validation-failed', $body->slug());
        self::assertArrayHasKey('name', $body->errorsByField());
        self::assertArrayHasKey('count', $body->errorsByField());
    }

    public function testValidPayloadIsAccepted(): void
    {
        $response = $this->jsonRequest('POST', '/api/_test/payload', ['name' => 'Ana', 'count' => 3]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('{"name":"Ana","count":3}', $response->getContent());
    }

    public function testMalformedJsonIsAnUnprocessableRequest(): void
    {
        $this->client->request('POST', '/api/_test/payload', server: ['CONTENT_TYPE' => 'application/json'], content: '{"name": ');
        $response = $this->client->getResponse();

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('malformed-request', $this->problem($response)->slug());
    }

    #[DataProvider('bodyMethods')]
    public function testBodyMethodsWithoutJsonContentTypeAreRejected(string $method): void
    {
        $this->client->request($method, '/api/_test/no-body', server: ['CONTENT_TYPE' => 'text/plain'], content: 'hello');
        $response = $this->client->getResponse();

        self::assertSame(415, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('unsupported-media-type', $this->problem($response)->slug());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bodyMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
    }

    public function testJsonRequestsPassTheContentTypeCheck(): void
    {
        self::assertSame(204, $this->jsonRequest('POST', '/api/_test/no-body')->getStatusCode());
    }

    public function testProblemTitlesFollowAcceptLanguageAndDefaultToSerbian(): void
    {
        $serbian = $this->problem($this->jsonRequest('GET', '/api/_test/not-found', headers: ['Accept-Language' => '']));
        $english = $this->problem($this->jsonRequest('GET', '/api/_test/not-found', headers: ['Accept-Language' => 'en']));
        $serbianExplicit = $this->problem($this->jsonRequest('GET', '/api/_test/not-found', headers: ['Accept-Language' => 'sr-Latn-RS,sr;q=0.9']));

        self::assertSame('Resurs nije pronađen', $serbian->title);
        self::assertSame('Resource not found', $english->title);
        self::assertSame('Resurs nije pronađen', $serbianExplicit->title);
    }

    public function testValidationMessagesAreTranslated(): void
    {
        $english = $this->problem($this->jsonRequest('POST', '/api/_test/payload', ['name' => ''], ['Accept-Language' => 'en']));
        $serbian = $this->problem($this->jsonRequest('POST', '/api/_test/payload', ['name' => ''], ['Accept-Language' => '']));

        self::assertSame('This value should not be blank.', $english->errorsByField()['name'] ?? null);
        self::assertNotSame('This value should not be blank.', $serbian->errorsByField()['name'] ?? null);
    }

    public function testCorrelationIdIsReturnedOnProblemResponses(): void
    {
        $response = $this->jsonRequest('GET', '/api/_test/not-found', headers: ['X-Correlation-Id' => 'req-123']);

        self::assertSame('req-123', $response->headers->get('X-Correlation-Id'));
    }

    public function testErrorsOutsideApiAreNotProblemResponses(): void
    {
        $this->client->catchExceptions(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside');

        $this->client->request('GET', '/outside-api/_test/boom');
    }

    public function testMoneyAndDatesUseTheApiConventions(): void
    {
        $response = $this->jsonRequest('GET', '/api/_test/serialized');

        self::assertSame(
            '{"price":{"amount":150000,"currency":"RSD"},"createdAt":"2026-03-01T11:30:00Z","someValue":1}',
            $response->getContent(),
        );
    }

    private function problem(Response $response): ProblemJson
    {
        return ProblemJson::fromJson((string) $response->getContent());
    }
}
