<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Health;

use App\SharedKernel\Infrastructure\Health\DatabaseHealthCheck;
use App\SharedKernel\Infrastructure\Health\HealthResult;
use App\SharedKernel\Infrastructure\Health\MessengerTransportHealthCheck;
use App\Tests\Support\Fake\CountableTransport;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Transport\TransportInterface;

#[CoversClass(HealthResult::class)]
#[CoversClass(DatabaseHealthCheck::class)]
#[CoversClass(MessengerTransportHealthCheck::class)]
final class HealthChecksTest extends TestCase
{
    public function testResultFactories(): void
    {
        self::assertTrue(HealthResult::healthy()->healthy);
        self::assertFalse(HealthResult::unhealthy()->healthy);
    }

    public function testDatabaseCheckIsHealthyWhenTheQuerySucceeds(): void
    {
        $connection = self::createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);

        $check = new DatabaseHealthCheck($connection);

        self::assertSame('database', $check->name());
        self::assertTrue($check->check()->healthy);
    }

    public function testDatabaseCheckIsUnhealthyWhenTheQueryFails(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchOne')->willThrowException(new RuntimeException('connection refused'));

        self::assertFalse((new DatabaseHealthCheck($connection))->check()->healthy);
    }

    public function testMessengerCheckCountsTheTransportMessages(): void
    {
        $transport = self::createMock(CountableTransport::class);
        $transport->expects(self::once())->method('getMessageCount')->willReturn(3);

        $check = new MessengerTransportHealthCheck($transport);

        self::assertSame('messenger', $check->name());
        self::assertTrue($check->check()->healthy);
    }

    public function testMessengerCheckIsUnhealthyWhenTheTransportIsUnreachable(): void
    {
        $transport = self::createStub(CountableTransport::class);
        $transport->method('getMessageCount')->willThrowException(new RuntimeException('table missing'));

        self::assertFalse((new MessengerTransportHealthCheck($transport))->check()->healthy);
    }

    public function testMessengerCheckAcceptsTransportsThatCannotCountMessages(): void
    {
        self::assertTrue((new MessengerTransportHealthCheck(self::createStub(TransportInterface::class)))->check()->healthy);
    }
}
