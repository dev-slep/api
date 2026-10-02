<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Http;

use App\Authentication\Infrastructure\Http\RequestContextFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function strlen;

use Symfony\Component\HttpFoundation\Request;

#[CoversClass(RequestContextFactory::class)]
final class RequestContextFactoryTest extends TestCase
{
    public function testTheClientAddressAndUserAgentAreTaken(): void
    {
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'Slep/1.0 (Android 15)']);

        $context = (new RequestContextFactory())->fromRequest($request);

        self::assertSame('203.0.113.7', $context->ip);
        self::assertSame('Slep/1.0 (Android 15)', $context->userAgent);
    }

    public function testAMissingUserAgentStaysNull(): void
    {
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7']);
        $request->headers->remove('User-Agent');

        self::assertNull((new RequestContextFactory())->fromRequest($request)->userAgent);
    }

    public function testAnOversizedUserAgentIsCut(): void
    {
        $request = Request::create('/', 'POST', server: ['HTTP_USER_AGENT' => str_repeat('é', 400)]);

        self::assertSame(255, mb_strlen((string) (new RequestContextFactory())->fromRequest($request)->userAgent));
    }

    public function testExactlyTheLimitIsKept(): void
    {
        $request = Request::create('/', 'POST', server: ['HTTP_USER_AGENT' => str_repeat('a', 255)]);

        self::assertSame(255, strlen((string) (new RequestContextFactory())->fromRequest($request)->userAgent));
    }
}
