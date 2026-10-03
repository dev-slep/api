<?php

declare(strict_types=1);

namespace App\Tests\Integration\Audit;

use App\Audit\Infrastructure\Messaging\RecordIntegrationEventSubscriber;
use App\Authentication\Contract\Event\LoginFailedV1;
use App\Authentication\Contract\Event\UserLoggedInV1;
use App\SharedKernel\Application\EventBus;
use App\SharedKernel\Contract\UserId;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\TruncatesSchemas;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Events travel through the real transport and are stored by the worker, which commits for real.
 */
#[CoversClass(RecordIntegrationEventSubscriber::class)]
#[SkipDatabaseRollback]
final class RecordIntegrationEventsTest extends AuthenticationIntegrationTestCase
{
    use TruncatesSchemas;

    private const string ACCOUNT = '01900000-0000-7000-8000-0000000000aa';

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

    public function testEventsOfOtherModulesAreStoredOnceEvenIfDeliveredTwice(): void
    {
        $bus = static::getContainer()->get(EventBus::class);
        $loggedIn = new UserLoggedInV1('01900000-0000-7000-8000-0000000000e1', new DateTimeImmutable('2026-01-01T12:00:00+00:00'), new UserId(self::ACCOUNT), 'password', '203.0.113.7', 'Agent/2');
        $bus->publish($loggedIn);
        $bus->publish($loggedIn);
        $bus->publish(new LoginFailedV1('01900000-0000-7000-8000-0000000000e2', new DateTimeImmutable('2026-01-01T12:01:00+00:00'), null, 'unknown-email', '203.0.113.8', null));

        $output = $this->consumeMessages('async', 4);

        self::assertSame(2, $this->countRows('audit.audit_entry'), $output);
        self::assertSame(2, $this->countRows('audit.processed_event'));
        $row = $this->connection()->fetchAssociative("SELECT * FROM audit.audit_entry WHERE name = 'authentication.user_logged_in.v1'");
        self::assertIsArray($row);
        self::assertSame('event', $row['kind']);
        self::assertSame('recorded', $row['outcome']);
        self::assertSame(self::ACCOUNT, $row['actor_id']);
        self::assertSame(self::ACCOUNT, $row['target_id']);
        self::assertSame('203.0.113.7', $row['ip']);
        self::assertSame('Agent/2', $row['user_agent']);
        self::assertNotNull($row['correlation_id']);
        self::assertSame(1, $this->countRows('audit.audit_entry', "name = 'authentication.login_failed.v1' AND actor_type = 'system' AND actor_id IS NULL"));
    }
}
