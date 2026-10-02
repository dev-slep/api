<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Infrastructure\Http\IdempotencyKeyReused;
use App\SharedKernel\Infrastructure\Http\IdempotentRequests;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(IdempotentRequests::class)]
#[CoversClass(IdempotencyKeyReused::class)]
final class IdempotentRequestsTest extends TestCase
{
    private int $calls = 0;
    private IdempotentRequests $idempotent;

    protected function setUp(): void
    {
        $this->calls = 0;
        $this->idempotent = new IdempotentRequests(new ArrayAdapter(), new LockFactory(new InMemoryStore()));
    }

    private function request(string $body = '{"a":1}', ?string $key = 'key-1', string $path = '/api/v1/things'): Request
    {
        $request = Request::create($path, 'POST', content: $body);
        if (null !== $key) {
            $request->headers->set('Idempotency-Key', $key);
        }

        return $request;
    }

    private function handle(Request $request, int $status = 201): Response
    {
        return $this->idempotent->run($request, function () use ($status): Response {
            ++$this->calls;

            return new JsonResponse(['call' => $this->calls], $status);
        });
    }

    public function testWithoutAKeyTheHandlerRunsEveryTime(): void
    {
        $first = $this->handle($this->request(key: null));
        $second = $this->handle($this->request(key: null));

        self::assertSame(2, $this->calls);
        self::assertSame('{"call":1}', $first->getContent());
        self::assertSame('{"call":2}', $second->getContent());
        self::assertNull($second->headers->get('Idempotency-Replayed'));
    }

    public function testTheSameKeyAndBodyReplaysTheFirstSuccessfulResponse(): void
    {
        $first = $this->handle($this->request());
        $replay = $this->handle($this->request());
        $again = $this->handle($this->request());

        self::assertSame(1, $this->calls, 'the handler ran once');
        self::assertSame(201, $replay->getStatusCode());
        self::assertSame($first->getContent(), $replay->getContent());
        self::assertSame($first->getContent(), $again->getContent());
        self::assertSame('true', $replay->headers->get('Idempotency-Replayed'));
        self::assertNull($first->headers->get('Idempotency-Replayed'));
        self::assertSame('application/json', $replay->headers->get('Content-Type'));
    }

    public function testTheSameKeyWithAnotherBodyIsRefused(): void
    {
        $this->handle($this->request('{"a":1}'));

        try {
            $this->handle($this->request('{"a":2}'));
            self::fail('A different request reused the key.');
        } catch (IdempotencyKeyReused $refusal) {
            self::assertSame('idempotency-key-reused', $refusal->problemSlug());
            self::assertSame(422, $refusal->httpStatus());
        }
        self::assertSame(1, $this->calls);
    }

    public function testTheKeyBelongsToThePathAndMethod(): void
    {
        $this->handle($this->request(path: '/api/v1/things'));
        $other = $this->handle($this->request(path: '/api/v1/other'));

        self::assertSame(2, $this->calls);
        self::assertNull($other->headers->get('Idempotency-Replayed'));
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $this->handle($this->request(key: 'key-1'));
        $this->handle($this->request(key: 'key-2'));

        self::assertSame(2, $this->calls);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function failures(): iterable
    {
        yield 'validation error' => [422];
        yield 'conflict' => [409];
        yield 'server error' => [500];
        yield 'unauthorized' => [401];
    }

    #[DataProvider('failures')]
    public function testAFailedResponseIsNotRememberedSoARetryRunsTheHandlerAgain(int $status): void
    {
        $this->handle($this->request(), $status);

        $retry = $this->handle($this->request(), 201);

        self::assertSame(2, $this->calls);
        self::assertSame(201, $retry->getStatusCode());
        self::assertNull($retry->headers->get('Idempotency-Replayed'));
    }

    public function testAnExceptionFromTheHandlerLeavesTheKeyUsable(): void
    {
        try {
            $this->idempotent->run($this->request(), static fn (): Response => throw new RuntimeException('boom'));
            self::fail('The failure was swallowed.');
        } catch (RuntimeException) {
        }

        self::assertSame(201, $this->handle($this->request())->getStatusCode());
        self::assertSame(1, $this->calls);
    }

    public function testAnOkResponseIsRememberedToo(): void
    {
        $this->handle($this->request(), 200);

        self::assertSame(200, $this->handle($this->request())->getStatusCode());
        self::assertSame(1, $this->calls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('k', 256)];
    }

    #[DataProvider('unusableKeys')]
    public function testAnEmptyOrOversizedKeyIsIgnored(string $key): void
    {
        $this->handle($this->request(key: $key));
        $this->handle($this->request(key: $key));

        self::assertSame(2, $this->calls);
    }

    public function testAKeyAtTheLengthLimitWorks(): void
    {
        $key = str_repeat('k', 255);

        $this->handle($this->request(key: $key));
        $this->handle($this->request(key: $key));

        self::assertSame(1, $this->calls);
    }

    public function testTheRefusalExplainsItself(): void
    {
        self::assertStringContainsString('different request', (new IdempotencyKeyReused())->getMessage());
    }
}
