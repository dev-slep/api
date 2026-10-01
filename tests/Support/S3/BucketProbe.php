<?php

declare(strict_types=1);

namespace App\Tests\Support\S3;

use const PHP_URL_HOST;
use const PHP_URL_PORT;

use function sprintf;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Checks that an S3-compatible bucket exists (signed HEAD request, AWS Signature V4, path-style).
 */
final readonly class BucketProbe
{
    public function __construct(
        private HttpClientInterface $http,
        private string $endpoint,
        private string $key,
        private string $secret,
        private string $region,
    ) {
    }

    public function exists(string $bucket): bool
    {
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        $hostHeader = null === $port ? $host : $host.':'.$port;

        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);
        $payloadHash = hash('sha256', '');
        $uri = '/'.$bucket;

        $canonicalHeaders = "host:$hostHeader\nx-amz-content-sha256:$payloadHash\nx-amz-date:$amzDate\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "HEAD\n$uri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        $scope = "$date/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n".hash('sha256', $canonicalRequest);

        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', 's3',
                hash_hmac('sha256', $this->region,
                    hash_hmac('sha256', $date, 'AWS4'.$this->secret, true),
                    true),
                true),
            true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $response = $this->http->request('HEAD', $this->endpoint.$uri, [
            'headers' => [
                'x-amz-content-sha256' => $payloadHash,
                'x-amz-date' => $amzDate,
                'Authorization' => sprintf(
                    'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
                    $this->key,
                    $scope,
                    $signedHeaders,
                    $signature,
                ),
            ],
        ]);

        return 200 === $response->getStatusCode();
    }
}
