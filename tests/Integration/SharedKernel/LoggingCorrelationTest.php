<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\TruncatesSchemas;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

use function is_array;
use function is_string;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
#[SkipDatabaseRollback]
final class LoggingCorrelationTest extends IntegrationTestCase
{
    use TruncatesSchemas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateSchemas();
    }

    protected function tearDown(): void
    {
        $this->truncateSchemas();

        parent::tearDown();
    }

    public function testAnHttpRequestAndTheMessageItTriggersLogTheSameCorrelationId(): void
    {
        $logFile = static::getContainer()->getParameter('kernel.logs_dir').'/test.log';
        file_put_contents($logFile, '');

        $this->jsonRequest('POST', '/api/_test/dispatch', headers: ['X-Correlation-Id' => 'trace-me-42']);
        $this->consumeMessages('async', 5);

        $records = $this->recordsWithCorrelationId($logFile, 'trace-me-42');
        $channels = array_unique(array_column($records, 'channel'));

        self::assertContains('request', $channels, 'the HTTP request logged with the correlation id');
        self::assertContains('messenger', $channels, 'the worker logged with the same correlation id');
    }

    /**
     * @return list<array{channel: string, message: string}>
     */
    private function recordsWithCorrelationId(string $logFile, string $correlationId): array
    {
        $records = [];
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);

        foreach ($lines as $line) {
            $record = json_decode($line, true);
            if (!is_array($record) || !is_array($record['extra'] ?? null)) {
                continue;
            }
            if (($record['extra']['correlationId'] ?? null) === $correlationId && is_string($record['channel'] ?? null) && is_string($record['message'] ?? null)) {
                $records[] = ['channel' => $record['channel'], 'message' => $record['message']];
            }
        }

        return $records;
    }
}
