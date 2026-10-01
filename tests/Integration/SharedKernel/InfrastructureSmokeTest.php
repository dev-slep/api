<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\S3\BucketProbe;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversNothing]
final class InfrastructureSmokeTest extends IntegrationTestCase
{
    public function testPostgresHasPostgis(): void
    {
        $version = $this->connection()->fetchOne('SELECT postgis_version()');

        self::assertIsString($version);
        self::assertStringStartsWith('3.', $version);
    }

    public function testAllSchemasExist(): void
    {
        /** @var list<string> $expected */
        $expected = static::getContainer()->getParameter('app.database_schemas');

        $actual = $this->connection()->fetchFirstColumn(
            'SELECT schema_name FROM information_schema.schemata WHERE schema_name IN (?)',
            [$expected],
            [ArrayParameterType::STRING],
        );

        self::assertCount(13, $expected);
        self::assertEqualsCanonicalizing($expected, $actual);
    }

    public function testWireMockIsReachableAndResettable(): void
    {
        $wireMock = $this->wireMock();
        $wireMock->stubFor(['request' => ['method' => 'GET', 'url' => '/smoke'], 'response' => ['status' => 204]]);

        self::assertSame(204, $this->http()->request('GET', self::env('WIREMOCK_URL').'/smoke')->getStatusCode());
        self::assertTrue($wireMock->verify(['method' => 'GET', 'url' => '/smoke'], 1));

        $wireMock->reset();
        self::assertSame(404, $this->http()->request('GET', self::env('WIREMOCK_URL').'/smoke')->getStatusCode());
    }

    public function testMinioBucketsExist(): void
    {
        $probe = new BucketProbe(
            $this->http(),
            self::env('S3_ENDPOINT'),
            self::env('S3_KEY'),
            self::env('S3_SECRET'),
            self::env('S3_REGION'),
        );

        self::assertTrue($probe->exists('tow-request-photos'));
        self::assertTrue($probe->exists('tower-photos'));
        self::assertFalse($probe->exists('no-such-bucket'));
    }

    public function testMailpitIsReachable(): void
    {
        $response = $this->http()->request('GET', self::env('MAILPIT_URL').'/api/v1/info');

        self::assertSame(200, $response->getStatusCode());
    }

    private function connection(): Connection
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }

    private function http(): HttpClientInterface
    {
        $http = static::getContainer()->get(HttpClientInterface::class);

        return $http;
    }
}
