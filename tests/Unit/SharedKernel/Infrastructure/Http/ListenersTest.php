<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Infrastructure\Http\ApiPath;
use App\SharedKernel\Infrastructure\Http\JsonOnlyListener;
use App\SharedKernel\Infrastructure\Http\LocaleListener;
use App\SharedKernel\Infrastructure\Http\ProblemDetailsFactory;
use App\SharedKernel\Infrastructure\Http\ProblemDetailsListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ApiPath::class)]
#[CoversClass(JsonOnlyListener::class)]
#[CoversClass(LocaleListener::class)]
#[CoversClass(ProblemDetailsListener::class)]
final class ListenersTest extends TestCase
{
    #[DataProvider('paths')]
    public function testApiPathMatching(string $path, bool $expected): void
    {
        self::assertSame($expected, ApiPath::matches($path));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'api root' => ['/api', true];
        yield 'versioned api' => ['/api/v1/tow-requests', true];
        yield 'health' => ['/health/live', false];
        yield 'lookalike prefix' => ['/apiary', false];
        yield 'nested api segment' => ['/x/api/y', false];
        yield 'root' => ['/', false];
    }

    #[DataProvider('bodyMethodsWithoutJson')]
    public function testBodyMethodsWithoutJsonAreRejected(string $method, ?string $contentType): void
    {
        $request = Request::create('/api/v1/x', $method, server: null === $contentType ? [] : ['CONTENT_TYPE' => $contentType], content: '{}');

        $this->expectException(UnsupportedMediaTypeHttpException::class);

        (new JsonOnlyListener())->onRequest($this->requestEvent($request));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function bodyMethodsWithoutJson(): iterable
    {
        yield 'POST without content type' => ['POST', null];
        yield 'PUT form' => ['PUT', 'application/x-www-form-urlencoded'];
        yield 'PATCH plain text' => ['PATCH', 'text/plain'];
        yield 'POST xml' => ['POST', 'application/xml'];
    }

    #[DataProvider('acceptedRequests')]
    public function testJsonAndNonBodyRequestsPass(string $uri, string $method, ?string $contentType): void
    {
        $request = Request::create($uri, $method, server: null === $contentType ? [] : ['CONTENT_TYPE' => $contentType]);

        (new JsonOnlyListener())->onRequest($this->requestEvent($request));

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function acceptedRequests(): iterable
    {
        yield 'POST json' => ['/api/v1/x', 'POST', 'application/json'];
        yield 'POST json with charset' => ['/api/v1/x', 'POST', 'application/json; charset=utf-8'];
        yield 'GET without content type' => ['/api/v1/x', 'GET', null];
        yield 'DELETE without content type' => ['/api/v1/x', 'DELETE', null];
        yield 'POST outside api' => ['/health/ready', 'POST', null];
    }

    public function testSubRequestsAreNotChecked(): void
    {
        $request = Request::create('/api/v1/x', 'POST');

        (new JsonOnlyListener())->onRequest($this->requestEvent($request, HttpKernelInterface::SUB_REQUEST));

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('acceptLanguages')]
    public function testLocaleResolution(?string $acceptLanguage, string $expected): void
    {
        $request = new Request();
        if (null !== $acceptLanguage) {
            $request->headers->set('Accept-Language', $acceptLanguage);
        }

        self::assertSame($expected, $this->localeListener()->resolve($request));
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function acceptLanguages(): iterable
    {
        yield 'no header' => [null, 'sr_Latn'];
        yield 'english' => ['en', 'en'];
        yield 'english with region' => ['en-GB,en;q=0.8', 'en'];
        yield 'serbian latin' => ['sr-Latn', 'sr_Latn'];
        yield 'serbian latin with region' => ['sr-Latn-RS', 'sr_Latn'];
        yield 'bare serbian' => ['sr', 'sr_Latn'];
        yield 'unsupported language' => ['fr-FR', 'sr_Latn'];
        yield 'first supported wins' => ['de, en;q=0.7, sr;q=0.5', 'en'];
        yield 'quality order is respected' => ['en;q=0.5, sr-Latn;q=0.9', 'sr_Latn'];
        yield 'garbage' => ['*;;;', 'sr_Latn'];
    }

    public function testLocaleListenerSetsTheRequestLocaleUnderApiOnly(): void
    {
        $api = Request::create('/api/v1/x', server: ['HTTP_ACCEPT_LANGUAGE' => 'sr-Latn']);
        $other = Request::create('/health/live', server: ['HTTP_ACCEPT_LANGUAGE' => 'sr-Latn']);
        $localeBefore = $other->getLocale();

        $this->localeListener()->onRequest($this->requestEvent($api));
        $this->localeListener()->onRequest($this->requestEvent($other));

        self::assertSame('sr_Latn', $api->getLocale());
        self::assertSame($localeBefore, $other->getLocale(), 'only /api requests are localised');
    }

    public function testLocaleListenerIgnoresSubRequests(): void
    {
        $request = Request::create('/api/v1/x', server: ['HTTP_ACCEPT_LANGUAGE' => 'sr-Latn']);
        $localeBefore = $request->getLocale();

        $this->localeListener()->onRequest($this->requestEvent($request, HttpKernelInterface::SUB_REQUEST));

        self::assertSame($localeBefore, $request->getLocale());
    }

    public function testProblemListenerReplacesTheResponseUnderApi(): void
    {
        $request = Request::create('/api/v1/x');
        $event = new ExceptionEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new NotFoundHttpException());

        $this->problemListener()->onException($event);

        self::assertSame(404, $event->getResponse()?->getStatusCode());
        self::assertSame('application/problem+json', $event->getResponse()->headers->get('Content-Type'));
    }

    public function testProblemListenerLeavesOtherPathsAlone(): void
    {
        $request = Request::create('/health/ready');
        $event = new ExceptionEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new NotFoundHttpException());

        $this->problemListener()->onException($event);

        self::assertNull($event->getResponse());
    }

    private function localeListener(): LocaleListener
    {
        return new LocaleListener(['sr_Latn', 'en'], 'sr_Latn');
    }

    private function problemListener(): ProblemDetailsListener
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('title');

        return new ProblemDetailsListener(new ProblemDetailsFactory($translator, 'https://slep.test/problems', false));
    }

    private function requestEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type);
    }
}
