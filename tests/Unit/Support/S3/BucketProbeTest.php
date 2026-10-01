<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\S3;

use App\Tests\Support\S3\BucketProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(BucketProbe::class)]
final class BucketProbeTest extends TestCase
{
    public function testExistingBucketReturnsTrueAndRequestIsSigned(): void
    {
        $method = $url = $authorization = null;
        $probe = $this->probe(static function (string $m, string $u, array $options) use (&$method, &$url, &$authorization): MockResponse {
            $method = $m;
            $url = $u;
            /** @var array{authorization: list<string>} $headers */
            $headers = $options['normalized_headers'];
            $authorization = $headers['authorization'][0];

            return new MockResponse('', ['http_code' => 200]);
        });

        self::assertTrue($probe->exists('photos'));
        self::assertSame('HEAD', $method);
        self::assertSame('http://minio:9000/photos', $url);
        self::assertIsString($authorization);
        self::assertMatchesRegularExpression(
            '#^Authorization: AWS4-HMAC-SHA256 Credential=key/\d{8}/us-east-1/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature=[0-9a-f]{64}$#',
            $authorization,
        );
    }

    public function testMissingBucketReturnsFalse(): void
    {
        $probe = $this->probe(static fn (): MockResponse => new MockResponse('', ['http_code' => 404]));

        self::assertFalse($probe->exists('nope'));
    }

    /**
     * @param callable(string, string, array<string, mixed>): MockResponse $responder
     */
    private function probe(callable $responder): BucketProbe
    {
        return new BucketProbe(new MockHttpClient($responder), 'http://minio:9000', 'key', 'secret', 'us-east-1');
    }
}
