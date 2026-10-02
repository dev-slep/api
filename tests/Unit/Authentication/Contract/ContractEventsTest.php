<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Contract;

use App\Authentication\Contract\Event\AuthenticationEvent;
use App\Authentication\Contract\Event\EmailVerifiedV1;
use App\Authentication\Contract\Event\LoginFailedV1;
use App\Authentication\Contract\Event\PasswordChangedV1;
use App\Authentication\Contract\Event\PasswordResetRequestedV1;
use App\Authentication\Contract\Event\RefreshTokenReuseDetectedV1;
use App\Authentication\Contract\Event\TwoFactorEnabledV1;
use App\Authentication\Contract\Event\TwoFactorFailedV1;
use App\Authentication\Contract\Event\TwoFactorVerifiedV1;
use App\Authentication\Contract\Event\UserLoggedInV1;
use App\Authentication\Contract\Event\UserLoggedOutV1;
use App\Authentication\Contract\Event\UserRegisteredV1;
use App\Penalty\Contract\Event\PenaltyEvent;
use App\Penalty\Contract\Event\UserBannedV1;
use App\Penalty\Contract\Event\UserSuspendedV1;
use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Contract\UserId;
use DateTimeImmutable;

use function dirname;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;

#[CoversClass(UserRegisteredV1::class)]
#[CoversClass(EmailVerifiedV1::class)]
#[CoversClass(UserLoggedInV1::class)]
#[CoversClass(LoginFailedV1::class)]
#[CoversClass(PasswordChangedV1::class)]
#[CoversClass(PasswordResetRequestedV1::class)]
#[CoversClass(RefreshTokenReuseDetectedV1::class)]
#[CoversClass(UserLoggedOutV1::class)]
#[CoversClass(TwoFactorEnabledV1::class)]
#[CoversClass(TwoFactorVerifiedV1::class)]
#[CoversClass(TwoFactorFailedV1::class)]
#[CoversClass(UserBannedV1::class)]
#[CoversClass(UserSuspendedV1::class)]
final class ContractEventsTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';
    private const array SECRET_LIKE = ['password', 'token', 'secret', 'code', 'hash'];

    private static function when(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01T12:00:00+00:00');
    }

    /**
     * @return iterable<int|string, array{IntegrationEvent, string, array<string, mixed>}>
     */
    public static function events(): iterable
    {
        $user = new UserId(self::USER);
        $at = self::when();

        yield 'registered' => [new UserRegisteredV1('e1', $at, $user, 'a@example.com', 'DRIVER', '+381641234567', 'en', false), 'authentication.user_registered.v1', ['email' => 'a@example.com', 'role' => 'DRIVER', 'phone' => '+381641234567', 'locale' => 'en', 'emailVerified' => false]];
        yield 'email verified' => [new EmailVerifiedV1('e1', $at, $user), 'authentication.email_verified.v1', []];
        yield 'logged in' => [new UserLoggedInV1('e1', $at, $user, 'pwd', '203.0.113.7', 'UA'), 'authentication.user_logged_in.v1', ['method' => 'pwd', 'ip' => '203.0.113.7', 'userAgent' => 'UA']];
        yield 'login failed' => [new LoginFailedV1('e1', $at, null, 'invalid_credentials', '203.0.113.7', null), 'authentication.login_failed.v1', ['accountId' => null, 'reason' => 'invalid_credentials', 'ip' => '203.0.113.7', 'userAgent' => null]];
        yield 'password changed' => [new PasswordChangedV1('e1', $at, $user), 'authentication.password_changed.v1', []];
        yield 'reset requested' => [new PasswordResetRequestedV1('e1', $at, $user, '203.0.113.7'), 'authentication.password_reset_requested.v1', ['ip' => '203.0.113.7']];
        yield 'token reuse' => [new RefreshTokenReuseDetectedV1('e1', $at, $user, 'family', '203.0.113.7', 'UA'), 'authentication.refresh_token_reuse_detected.v1', ['familyId' => 'family']];
        yield 'logged out' => [new UserLoggedOutV1('e1', $at, $user, true), 'authentication.user_logged_out.v1', ['allDevices' => true]];
        yield 'two factor enabled' => [new TwoFactorEnabledV1('e1', $at, $user), 'authentication.two_factor_enabled.v1', []];
        yield 'two factor verified' => [new TwoFactorVerifiedV1('e1', $at, $user, 'totp', '203.0.113.7', 'UA'), 'authentication.two_factor_verified.v1', ['method' => 'totp']];
        yield 'two factor failed' => [new TwoFactorFailedV1('e1', $at, $user, '203.0.113.7', 'UA'), 'authentication.two_factor_failed.v1', []];
        yield 'banned' => [new UserBannedV1('e1', $at, $user, 'no-shows'), 'penalty.user_banned.v1', ['reason' => 'no-shows']];
        yield 'suspended' => [new UserSuspendedV1('e1', $at, $user, 'no-shows'), 'penalty.user_suspended.v1', ['reason' => 'no-shows']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('events')]
    public function testEveryEventIsVersionedAndCarriesExactlyItsFields(IntegrationEvent $event, string $name, array $payload): void
    {
        self::assertSame('e1', $event->eventId());
        self::assertSame($name, $event->eventName());
        self::assertSame(1, $event->version());
        self::assertStringEndsWith('.v1', $event->eventName());
        self::assertEquals(self::when(), $event->occurredAt());
        foreach ($payload as $field => $value) {
            self::assertSame($value, (new ReflectionProperty($event, $field))->getValue($event), $field);
        }
    }

    /**
     * @return iterable<int|string, array{IntegrationEvent}>
     */
    public static function eventObjects(): iterable
    {
        foreach (self::events() as $name => [$event]) {
            yield $name => [$event];
        }
    }

    #[DataProvider('eventObjects')]
    public function testEventsAreReadonlyAndSurviveSerialisation(IntegrationEvent $event): void
    {
        self::assertTrue((new ReflectionClass($event))->isReadOnly());
        self::assertEquals($event, unserialize(serialize($event)));
    }

    public function testNoEventCarriesASecretLikeField(): void
    {
        /** @var list<class-string> $classes */
        $classes = [];
        foreach ((new Finder())->files()->in(dirname(__DIR__, 4).'/src/Authentication/Contract/Event')->name('*V1.php') as $file) {
            $class = 'App\\Authentication\\Contract\\Event\\'.$file->getBasename('.php');
            self::assertTrue(class_exists($class), "$class does not exist");
            $classes[] = $class;
        }
        self::assertCount(11, $classes, 'a new Authentication event must be added to ContractEventsTest::events()');

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            self::assertTrue($reflection->isSubclassOf(AuthenticationEvent::class), $class);
            self::assertTrue($reflection->isFinal(), $class);
            foreach ($reflection->getProperties() as $property) {
                foreach (self::SECRET_LIKE as $word) {
                    self::assertStringNotContainsStringIgnoringCase($word, $property->getName(), "$class::\${$property->getName()} looks like a secret");
                }
            }
        }
    }

    public function testPenaltyEventsShareTheirBase(): void
    {
        foreach ([UserBannedV1::class, UserSuspendedV1::class] as $class) {
            $parent = (new ReflectionClass($class))->getParentClass();
            self::assertNotFalse($parent);
            self::assertSame(PenaltyEvent::class, $parent->getName());
        }
        self::assertSame(['userId', 'reason'], array_map(static fn (ReflectionProperty $property): string => $property->getName(), (new ReflectionClass(PenaltyEvent::class))->getProperties(ReflectionProperty::IS_PUBLIC)));
    }
}
