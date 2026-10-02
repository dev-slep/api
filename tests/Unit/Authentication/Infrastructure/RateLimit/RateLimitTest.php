<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\RateLimit;

use App\Authentication\Contract\CurrentUser;
use App\Authentication\Infrastructure\RateLimit\DbalRateLimiterStorage;
use App\Authentication\Infrastructure\RateLimit\RateLimitListener;
use App\SharedKernel\Contract\UserId;
use ArrayObject;
use Doctrine\DBAL\Connection;

use function is_string;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Policy\SlidingWindow;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RateLimitRows
{
    /** @var array<string, array{state: string, expires: string|null}> */
    public array $rows = [];
}

final class StateWithoutExpiry implements LimiterStateInterface
{
    public function getId(): string
    {
        return 'forever';
    }

    public function getExpirationTime(): ?int
    {
        return null;
    }
}

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(RateLimitListener::class)]
#[CoversClass(DbalRateLimiterStorage::class)]
final class RateLimitTest extends TestCase
{
    private const string ACCOUNT = '01900000-0000-7000-8000-0000000000aa';

    private function listener(int $ipLimit = 100, int $emailLimit = 2, ?string $account = null): RateLimitListener
    {
        $storage = new InMemoryStorage();
        $factory = static fn (string $id, int $limit): RateLimiterFactory => new RateLimiterFactory(['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 hour'], $storage);

        $currentUser = self::createStub(CurrentUser::class);
        $currentUser->method('isAuthenticated')->willReturn(null !== $account);
        if (null !== $account) {
            $currentUser->method('id')->willReturn(new UserId($account));
        }

        return new RateLimitListener(
            $factory('login_ip', $ipLimit),
            $factory('login_email', $emailLimit),
            $factory('register_ip', $emailLimit),
            $factory('password_ip', $ipLimit),
            $factory('password_email', $emailLimit),
            $factory('email_ip', $ipLimit),
            $factory('email_email', $emailLimit),
            $factory('social_ip', $emailLimit),
            $factory('token_ip', $emailLimit),
            $factory('two_factor_account', $emailLimit),
            $factory('two_factor_ip', $ipLimit),
            $currentUser,
        );
    }

    private function event(string $route, string $body = '', string $method = 'POST', string $ip = '203.0.113.7', int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/api/v1/x', $method, server: ['REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'application/json'], content: $body);
        $request->attributes->set('_route', $route);

        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type);
    }

    private function assertTooMany(RateLimitListener $listener, RequestEvent $event): void
    {
        try {
            $listener->onRequest($event);
            self::fail('The request was not limited.');
        } catch (TooManyRequestsHttpException $failure) {
            self::assertSame(429, $failure->getStatusCode());
            $retryAfter = $failure->getHeaders()['Retry-After'] ?? null;
            self::assertIsNumeric($retryAfter);
            self::assertGreaterThanOrEqual(1, (int) $retryAfter);
            self::assertLessThanOrEqual(3600, (int) $retryAfter);
        }
    }

    public function testLoginIsLimitedPerEmailAddress(): void
    {
        $listener = $this->listener(emailLimit: 2);
        $body = '{"email": "Ana@Example.com", "password": "x"}';

        $listener->onRequest($this->event('auth_login', $body));
        $listener->onRequest($this->event('auth_login', str_replace('Ana', 'ANA', $body), ip: '198.51.100.1'));

        $this->assertTooMany($listener, $this->event('auth_login', '{"email": " ana@example.com "}', ip: '198.51.100.2'));
    }

    public function testAnotherEmailAddressHasItsOwnBudget(): void
    {
        $listener = $this->listener(emailLimit: 1);
        $listener->onRequest($this->event('auth_login', '{"email": "ana@example.com"}'));

        $listener->onRequest($this->event('auth_login', '{"email": "bob@example.com"}'));

        $this->addToAssertionCount(1);
    }

    public function testLoginIsLimitedPerClientAddress(): void
    {
        $listener = $this->listener(ipLimit: 2, emailLimit: 100);

        $listener->onRequest($this->event('auth_login', '{"email": "a@example.com"}'));
        $listener->onRequest($this->event('auth_login', '{"email": "b@example.com"}'));

        $this->assertTooMany($listener, $this->event('auth_login', '{"email": "c@example.com"}'));
        $listener->onRequest($this->event('auth_login', '{"email": "c@example.com"}', ip: '198.51.100.9'));
    }

    /**
     * @return iterable<int|string, array{string, string}>
     */
    public static function limitedRoutes(): iterable
    {
        yield 'register' => ['auth_register', '{"email": "a@example.com"}'];
        yield 'password forgot' => ['auth_password_forgot', '{"email": "a@example.com"}'];
        yield 'email resend' => ['auth_email_resend', '{"email": "a@example.com"}'];
        yield 'social login' => ['auth_social_login', '{}'];
        yield 'password reset' => ['auth_password_reset', '{}'];
        yield 'email verify' => ['auth_email_verify', '{}'];
    }

    #[DataProvider('limitedRoutes')]
    public function testEveryLimitedRouteEventuallyAnswers429(string $route, string $body): void
    {
        $listener = $this->listener(ipLimit: 2, emailLimit: 2);

        $limited = false;
        for ($i = 0; $i < 4 && !$limited; ++$i) {
            try {
                $listener->onRequest($this->event($route, $body));
            } catch (TooManyRequestsHttpException) {
                $limited = true;
            }
        }

        self::assertTrue($limited, "$route was never limited");
    }

    public function testTheSecondFactorIsLimitedPerAccountEvenFromDifferentAddresses(): void
    {
        $listener = $this->listener(emailLimit: 2, account: self::ACCOUNT);

        $listener->onRequest($this->event('admin_auth_2fa_verify', ip: '198.51.100.1'));
        $listener->onRequest($this->event('admin_auth_2fa_verify', ip: '198.51.100.2'));

        $this->assertTooMany($listener, $this->event('admin_auth_2fa_verify', ip: '198.51.100.3'));
    }

    public function testRoutesWithoutLimitsAndOtherMethodsAreNotCounted(): void
    {
        $listener = $this->listener(ipLimit: 1, emailLimit: 1);

        for ($i = 0; $i < 5; ++$i) {
            $listener->onRequest($this->event('auth_refresh', '{"email": "a@example.com"}'));
            $listener->onRequest($this->event('auth_login', '{"email": "a@example.com"}', 'GET'));
            $listener->onRequest($this->event('some_other_route'));
        }

        $this->addToAssertionCount(1);
    }

    public function testSubRequestsAreNotCounted(): void
    {
        $listener = $this->listener(emailLimit: 1);

        for ($i = 0; $i < 3; ++$i) {
            $listener->onRequest($this->event('auth_register', type: HttpKernelInterface::SUB_REQUEST));
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function unusableBodies(): iterable
    {
        yield 'not json' => ['not json'];
        yield 'empty' => [''];
        yield 'json list' => ['[1, 2]'];
        yield 'email is a number' => ['{"email": 5}'];
        yield 'blank email' => ['{"email": "   "}'];
        yield 'missing email' => ['{"password": "x"}'];
    }

    #[DataProvider('unusableBodies')]
    public function testARequestWithoutAUsableEmailIsOnlyLimitedByAddress(string $body): void
    {
        $listener = $this->listener(ipLimit: 100, emailLimit: 1);

        for ($i = 0; $i < 3; ++$i) {
            $listener->onRequest($this->event('auth_login', $body));
        }

        $this->addToAssertionCount(1);
    }

    public function testAnAnonymousTwoFactorRequestIsOnlyLimitedByAddress(): void
    {
        $listener = $this->listener(ipLimit: 100, emailLimit: 1, account: null);

        for ($i = 0; $i < 3; ++$i) {
            $listener->onRequest($this->event('admin_auth_2fa_verify'));
        }

        $this->addToAssertionCount(1);
    }

    private function connection(RateLimitRows $table): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql, array $params = []) use ($table): int {
            $id = is_string($params['id'] ?? null) ? $params['id'] : '';
            if (str_starts_with($sql, 'INSERT')) {
                $table->rows[$id] = ['state' => is_string($params['state'] ?? null) ? $params['state'] : '', 'expires' => is_string($params['expires'] ?? null) ? $params['expires'] : null];
            } elseif (str_starts_with($sql, 'DELETE FROM authentication.rate_limit WHERE id')) {
                unset($table->rows[$id]);
            }

            return 1;
        });
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql, array $params) use ($table): string|false {
            $id = is_string($params['id'] ?? null) ? $params['id'] : '';

            return isset($table->rows[$id]) ? $table->rows[$id]['state'] : false;
        });

        return $connection;
    }

    public function testTheLimiterStateRoundTripsThroughTheDatabase(): void
    {
        $table = new RateLimitRows();
        $storage = new DbalRateLimiterStorage($this->connection($table));
        $state = new SlidingWindow('key', 3600);

        $storage->save($state);
        $fetched = $storage->fetch('key');

        self::assertInstanceOf(SlidingWindow::class, $fetched);
        self::assertSame('key', $fetched->getId());
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\+00:00$/', (string) $table->rows['key']['expires']);
    }

    public function testAnUnknownStateIsNull(): void
    {
        $table = new RateLimitRows();

        self::assertNull((new DbalRateLimiterStorage($this->connection($table)))->fetch('nothing'));
    }

    public function testDeletingRemovesTheState(): void
    {
        $table = new RateLimitRows();
        $storage = new DbalRateLimiterStorage($this->connection($table));
        $storage->save(new SlidingWindow('key', 60));

        $storage->delete('key');

        self::assertNull($storage->fetch('key'));
    }

    public function testAStateWithoutExpiryIsStoredWithoutOne(): void
    {
        $table = new RateLimitRows();
        $storage = new DbalRateLimiterStorage($this->connection($table));
        $storage->save(new StateWithoutExpiry());

        self::assertNull($table->rows['forever']['expires']);
    }

    public function testOnlyTheLimiterStateClassesAreRestoredFromTheDatabase(): void
    {
        $table = new RateLimitRows();
        $table->rows['evil'] = ['state' => base64_encode(serialize(new ArrayObject(['x']))), 'expires' => null];

        self::assertNull((new DbalRateLimiterStorage($this->connection($table)))->fetch('evil'));
    }

    public function testCorruptStateIsNull(): void
    {
        $table = new RateLimitRows();
        $table->rows['bad'] = ['state' => '%%% not base64 %%%', 'expires' => null];

        self::assertNull((new DbalRateLimiterStorage($this->connection($table)))->fetch('bad'));
    }
}
