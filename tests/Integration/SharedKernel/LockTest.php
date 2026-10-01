<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\StoreFactory;

#[CoversNothing]
final class LockTest extends IntegrationTestCase
{
    public function testSecondConcurrentAcquisitionOnPostgresFails(): void
    {
        // Two factories = two database sessions, like two workers
        $first = new LockFactory(StoreFactory::createStore(self::env('LOCK_DSN')))->createLock('scheduler-test', 30, false);
        $second = new LockFactory(StoreFactory::createStore(self::env('LOCK_DSN')))->createLock('scheduler-test', 30, false);

        try {
            self::assertTrue($first->acquire());
            self::assertFalse($second->acquire(), 'the lock is held by the first worker');

            $first->release();

            self::assertTrue($second->acquire(), 'the lock can be taken once released');
        } finally {
            $first->release();
            $second->release();
        }
    }

    public function testApplicationLockFactoryUsesThePostgresStore(): void
    {
        $factory = static::getContainer()->get('lock.factory');

        $lock = $factory->createLock('integration-test', 30, false);

        try {
            self::assertTrue($lock->acquire());
        } finally {
            $lock->release();
        }
    }
}
