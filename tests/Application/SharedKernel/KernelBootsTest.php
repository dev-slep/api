<?php

declare(strict_types=1);

namespace App\Tests\Application\SharedKernel;

use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;
use App\Tests\Support\ApplicationTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class KernelBootsTest extends ApplicationTestCase
{
    public function testKernelBootsWithoutTouchingTheDatabase(): void
    {
        $container = static::getContainer();

        self::assertSame('test', $container->getParameter('kernel.environment'));

        /** @var Connection $connection */
        $connection = $container->get('doctrine.dbal.default_connection');
        self::assertFalse($connection->isConnected());
        self::assertSame('database.invalid', $connection->getParams()['host'] ?? null);
    }

    public function testClockIsFrozenAndIdsAreSequential(): void
    {
        $container = static::getContainer();

        $clock = $container->get(Clock::class);
        $ids = $container->get(IdGenerator::class);

        self::assertSame('2026-01-01T12:00:00+00:00', $clock->now()->format('c'));
        self::assertSame('01900000-0000-7000-8000-000000000001', $ids->generate());
    }
}
