<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Infrastructure\Http\ProblemDetailsFactory;
use App\Tests\Support\Fixtures\Http\FixtureProblemException;
use App\Tests\Support\Fixtures\Messaging\FixtureDomainException;
use App\Tests\Support\ProblemJson;

use function array_key_exists;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[CoversClass(ProblemDetailsFactory::class)]
final class ProblemDetailsFactoryTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('mappings')]
    public function testExceptionMapping(Throwable $exception, int $status, string $slug, array $expected = []): void
    {
        $response = $this->factory()->create($exception, '/api/v1/things');
        $body = ProblemJson::fromJson((string) $response->getContent());

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('https://slep.test/problems/'.$slug, $body->type);
        self::assertSame($slug.' (title)', $body->title);
        self::assertSame($status, $body->status);
        self::assertSame('/api/v1/things', $body->instance);
        if (array_key_exists('detail', $expected)) {
            self::assertSame($expected['detail'], $body->detail);
        }
    }

    /**
     * @return iterable<string, array{Throwable, int, string, 3?: array<string, mixed>}>
     */
    public static function mappings(): iterable
    {
        yield 'domain exception with problem type' => [new FixtureProblemException('Conflict!'), 409, 'fixture-conflict', ['detail' => 'Conflict!']];
        yield 'plain domain exception' => [new FixtureDomainException('Rule broken'), 422, 'domain-error', ['detail' => 'Rule broken']];
        yield 'not found' => [new NotFoundHttpException('Gone'), 404, 'not-found', ['detail' => 'Gone']];
        yield 'access denied' => [new AccessDeniedHttpException(), 403, 'forbidden'];
        yield 'unauthenticated' => [new UnauthorizedHttpException('Bearer'), 401, 'unauthorized'];
        yield 'method not allowed' => [new MethodNotAllowedHttpException(['GET']), 405, 'method-not-allowed'];
        yield 'unsupported media type' => [new UnsupportedMediaTypeHttpException('json please'), 415, 'unsupported-media-type'];
        yield 'too many requests' => [new TooManyRequestsHttpException(), 429, 'too-many-requests'];
        yield 'other client error passes through' => [new HttpException(409, 'Conflict'), 409, 'bad-request'];
        yield 'other server http error' => [new HttpException(503, 'Down'), 503, 'internal-error'];
        yield 'malformed json' => [new BadRequestHttpException('x', new NotEncodableValueException('bad')), 422, 'malformed-request'];
        yield 'plain bad request' => [new BadRequestHttpException('Nope'), 400, 'bad-request'];
        yield 'unexpected error' => [new RuntimeException('secret'), 500, 'internal-error'];
    }

    public function testValidationFailureListsEveryViolation(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Name must not be blank.', null, [], null, 'name', ''),
            new ConstraintViolation('Count is too large.', null, [], null, 'count', 11),
        ]);
        $exception = new HttpException(422, 'Unprocessable', new ValidationFailedException(new stdClass(), $violations));

        $response = $this->factory()->create($exception, '/api/v1/things');
        $body = ProblemJson::fromJson((string) $response->getContent());

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('validation-failed', $body->slug());
        self::assertSame(
            [['field' => 'name', 'message' => 'Name must not be blank.'], ['field' => 'count', 'message' => 'Count is too large.']],
            $body->errors,
        );
        self::assertNull($body->detail);
    }

    public function testInternalDetailsAreHiddenOutsideDebug(): void
    {
        $content = (string) $this->factory(debug: false)->create(new RuntimeException('secret'), '/api/x')->getContent();

        self::assertNull(ProblemJson::fromJson($content)->detail);
        self::assertStringNotContainsString('secret', $content);
    }

    public function testInternalDetailsAreShownInDebug(): void
    {
        $body = ProblemJson::fromJson((string) $this->factory(debug: true)->create(new RuntimeException('secret'), '/api/x')->getContent());

        self::assertSame('RuntimeException: secret', $body->detail);
    }

    public function testTitleIsTranslatedForTheRequestedLocale(): void
    {
        $response = $this->factory()->create(new NotFoundHttpException(), '/api/x', 'en');

        self::assertStringContainsString('not-found (title,en)', (string) $response->getContent());
    }

    public function testTypeBaseUriTrailingSlashIsNormalised(): void
    {
        $factory = new ProblemDetailsFactory($this->translator(), 'https://slep.test/problems///', false);

        $body = ProblemJson::fromJson((string) $factory->create(new NotFoundHttpException(), '/api/x')->getContent());

        self::assertSame('https://slep.test/problems/not-found', $body->type);
    }

    public function testHeadersOfHttpExceptionsAreKept(): void
    {
        $response = $this->factory()->create(new UnauthorizedHttpException('Bearer realm="slep"'), '/api/x');

        self::assertSame('Bearer realm="slep"', $response->headers->get('WWW-Authenticate'));
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    private function factory(bool $debug = false): ProblemDetailsFactory
    {
        return new ProblemDetailsFactory($this->translator(), 'https://slep.test/problems', $debug);
    }

    private function translator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string => $id.' (title'.(null === $locale ? '' : ','.$locale).')',
        );

        return $translator;
    }
}
