<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Infrastructure\Messaging\RevokeSessionsOnPenaltySubscriber;
use App\Penalty\Contract\Event\UserBannedV1;
use App\Penalty\Contract\Event\UserSuspendedV1;
use App\SharedKernel\Application\EventBus;
use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Contract\UserId;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\TruncatesSchemas;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Penalty events travel through the real transport and are handled by the worker, which commits for real.
 */
#[CoversClass(RevokeSessionsOnPenaltySubscriber::class)]
#[SkipDatabaseRollback]
final class RevokeSessionsOnPenaltyTest extends AuthenticationIntegrationTestCase
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

    private function publish(IntegrationEvent $event): void
    {
        $bus = static::getContainer()->get(EventBus::class);
        $bus->publish($event);
    }

    public function testABanBansTheAccountAndEndsItsSessions(): void
    {
        $account = $this->createAccount();
        $tokens = $this->login($account->email()->toString());
        $other = $this->login($this->createAccount()->email()->toString());
        $this->connection()->executeStatement('DELETE FROM messenger.messenger_messages');

        $this->publish(new UserBannedV1('01900000-0000-7000-8000-0000000000e1', new DateTimeImmutable(), new UserId($account->id()->toString()), 'no-shows'));
        $output = $this->consumeMessages('async', 3);

        self::assertSame('BANNED', $this->accountColumn($account->email()->toString(), 'status'), $output);
        self::assertSame(401, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']])->getStatusCode());
        self::assertSame(200, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $other['refreshToken']])->getStatusCode(), 'other users keep their sessions');
        self::assertSame(1, $this->countRows('authentication.processed_event'));
    }

    public function testASuspensionEndsTheSessionsButDoesNotBan(): void
    {
        $account = $this->createAccount();
        $tokens = $this->login($account->email()->toString());
        $this->connection()->executeStatement('DELETE FROM messenger.messenger_messages');

        $this->publish(new UserSuspendedV1('01900000-0000-7000-8000-0000000000e2', new DateTimeImmutable(), new UserId($account->id()->toString()), 'no-shows'));
        $this->consumeMessages('async', 3);

        self::assertSame('ACTIVE', $this->accountColumn($account->email()->toString(), 'status'));
        self::assertSame(401, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']])->getStatusCode());
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $account->email()->toString(), 'password' => self::PASSWORD])->getStatusCode(), 'logging in again is allowed after a suspension is lifted by Penalty');
    }

    public function testARedeliveredEventIsHandledOnce(): void
    {
        $account = $this->createAccount();
        $event = new UserBannedV1('01900000-0000-7000-8000-0000000000e3', new DateTimeImmutable(), new UserId($account->id()->toString()), 'no-shows');
        $this->connection()->executeStatement('DELETE FROM messenger.messenger_messages');

        $this->publish($event);
        $this->publish($event);
        $this->consumeMessages('async', 3);

        self::assertSame(1, $this->countRows('authentication.processed_event'));
        self::assertSame('BANNED', $this->accountColumn($account->email()->toString(), 'status'));
        self::assertSame(0, $this->countRows('messenger.messenger_messages', "queue_name = 'failed'"));
    }

    public function testABanForAnUnknownUserIsAcceptedQuietly(): void
    {
        $this->publish(new UserBannedV1('01900000-0000-7000-8000-0000000000e4', new DateTimeImmutable(), new UserId('01900000-0000-7000-8000-00000000ffff'), 'x'));

        $this->consumeMessages('async', 3);

        self::assertSame(0, $this->countRows('messenger.messenger_messages', "queue_name = 'failed'"));
        self::assertSame(1, $this->countRows('authentication.processed_event'));
    }
}
