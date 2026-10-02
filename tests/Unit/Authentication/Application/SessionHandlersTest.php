<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Application;

use App\Authentication\Application\Command\BanAccount;
use App\Authentication\Application\Command\BanAccountHandler;
use App\Authentication\Application\Command\Login;
use App\Authentication\Application\Command\LoginHandler;
use App\Authentication\Application\Command\Logout;
use App\Authentication\Application\Command\LogoutHandler;
use App\Authentication\Application\Command\RefreshTokens;
use App\Authentication\Application\Command\RefreshTokensHandler;
use App\Authentication\Application\Command\RequestContext;
use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Application\Command\RevokeAllRefreshTokens;
use App\Authentication\Application\Command\RevokeAllRefreshTokensHandler;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Application\Service\SecurityEvents;
use App\Authentication\Application\Service\SessionFactory;
use App\Authentication\Contract\Event\LoginFailedV1;
use App\Authentication\Contract\Event\RefreshTokenReuseDetectedV1;
use App\Authentication\Contract\Event\UserLoggedInV1;
use App\Authentication\Contract\Event\UserLoggedOutV1;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\RefreshTokenStatus;
use App\Tests\Support\Authentication\AuthenticationWorld;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function strlen;

#[CoversClass(LoginHandler::class)]
#[CoversClass(RefreshTokensHandler::class)]
#[CoversClass(LogoutHandler::class)]
#[CoversClass(RevokeAllRefreshTokensHandler::class)]
#[CoversClass(BanAccountHandler::class)]
#[CoversClass(SessionFactory::class)]
#[CoversClass(SecurityEvents::class)]
#[CoversClass(AuthenticationResult::class)]
final class SessionHandlersTest extends TestCase
{
    private AuthenticationWorld $world;
    private RequestContext $context;

    protected function setUp(): void
    {
        $this->world = new AuthenticationWorld();
        $this->context = new RequestContext('203.0.113.7', 'PHPUnit');
    }

    private function login(string $email = 'ana@example.com', string $password = AuthenticationWorld::PASSWORD): AuthenticationResult
    {
        return ($this->world->login())(new Login($email, $password, $this->context));
    }

    private function refresh(string $token): AuthenticationResult
    {
        return ($this->world->refresh())(new RefreshTokens($token, $this->context));
    }

    public function testAVerifiedAccountLogsInAndGetsBothTokens(): void
    {
        $account = $this->world->verifiedAccount();

        $result = $this->login();

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isTwoFactorRequired());
        self::assertNotNull($result->tokens);
        self::assertSame(sprintf('access|%s|ROLE_DRIVER|pwd', $account->id()->toString()), $result->tokens->accessToken);
        self::assertSame(900, $result->tokens->expiresInSeconds);
        self::assertGreaterThanOrEqual(20, strlen($result->tokens->refreshToken));
        self::assertSame($result, $result->orThrow());
    }

    public function testTheEmailIsMatchedCaseInsensitively(): void
    {
        $this->world->verifiedAccount('ana@example.com');

        self::assertTrue($this->login('  ANA@Example.com ')->isSuccess());
    }

    public function testOnlyTheHashOfTheRefreshTokenIsStored(): void
    {
        $this->world->verifiedAccount();

        $result = $this->login();

        $stored = $this->world->refreshTokens->findByHash($this->world->tokenHasher->hash($result->tokens->refreshToken ?? ''));
        self::assertNotNull($stored);
        self::assertEquals($this->world->clock->now()->modify('+30 days'), $stored->expiresAt());
    }

    public function testLoggingInPublishesALoginEventWithTheRequestContext(): void
    {
        $account = $this->world->verifiedAccount();

        $this->login();

        $events = $this->world->eventBus->of(UserLoggedInV1::class);
        self::assertCount(1, $events);
        self::assertSame($account->id()->toString(), $events[0]->accountId->toString());
        self::assertSame('pwd', $events[0]->method);
        self::assertSame('203.0.113.7', $events[0]->ip);
        self::assertSame('PHPUnit', $events[0]->userAgent);
    }

    public function testTheRoleOfTheAccountIsUsedWhenAuthorizationGrantsNone(): void
    {
        $this->world->verifiedAccount('tow@example.com', AccountRole::Tower);

        self::assertStringContainsString('ROLE_TOWER', $this->login('tow@example.com')->tokens->accessToken ?? '');
    }

    public function testRolesGrantedByAuthorizationWinOverTheRegisteredRole(): void
    {
        $account = $this->world->verifiedAccount();
        $this->world->roles->roles[$account->id()->toString()] = ['ROLE_DRIVER', 'ROLE_TOWER'];

        self::assertStringContainsString('|ROLE_DRIVER,ROLE_TOWER|', $this->login()->tokens->accessToken ?? '');
    }

    /**
     * @return iterable<int|string, array{string, string}>
     */
    public static function badCredentials(): iterable
    {
        yield 'wrong password' => ['ana@example.com', 'wrong password!'];
        yield 'unknown email' => ['nobody@example.com', AuthenticationWorld::PASSWORD];
        yield 'malformed email' => ['not an email', AuthenticationWorld::PASSWORD];
        yield 'empty password' => ['ana@example.com', ''];
        yield 'oversized password' => ['ana@example.com', 'x'];
    }

    #[DataProvider('badCredentials')]
    public function testBadCredentialsAreRefusedWithTheSameProblemAndAFailureEvent(string $email, string $password): void
    {
        $this->world->verifiedAccount();
        if ('x' === $password) {
            $password = str_repeat('x', 500);
        }

        $result = $this->login($email, $password);

        self::assertFalse($result->isSuccess());
        self::assertSame('invalid-credentials', $result->failure?->problemSlug());
        $events = $this->world->eventBus->of(LoginFailedV1::class);
        self::assertCount(1, $events);
        self::assertSame('invalid_credentials', $events[0]->reason);
        self::assertSame('203.0.113.7', $events[0]->ip);
        self::assertSame([], $this->world->eventBus->of(UserLoggedInV1::class));
        self::assertSame([], $this->world->refreshTokens->all());
    }

    public function testAFailureEventNamesTheAccountOnlyWhenItExists(): void
    {
        $account = $this->world->verifiedAccount();

        $this->login('nobody@example.com');
        $this->login('ana@example.com', 'wrong password!');

        $events = $this->world->eventBus->of(LoginFailedV1::class);
        self::assertNull($events[0]->accountId);
        self::assertSame($account->id()->toString(), $events[1]->accountId?->toString());
    }

    public function testFailureResultsThrowTheirProblem(): void
    {
        $result = $this->login('nobody@example.com');

        $this->expectException(\App\Authentication\Domain\Exception\AuthenticationProblem::class);

        $result->orThrow();
    }

    public function testASocialOnlyAccountCannotLogInWithAPassword(): void
    {
        $this->world->social->trust('tok', \App\Authentication\Domain\Model\SocialProvider::Google, 'g-1', 'soc@example.com');
        ($this->world->socialLogin())(new \App\Authentication\Application\Command\SocialLogin('google', 'tok', 'DRIVER', null, 'en', $this->context));

        $result = $this->login('soc@example.com', 'anything at all');

        self::assertSame('invalid-credentials', $result->failure?->problemSlug());
    }

    public function testAnUnverifiedEmailCannotLogInEvenWithTheRightPassword(): void
    {
        ($this->world->register())(new \App\Authentication\Application\Command\RegisterUser('new@example.com', 'correct horse', 'DRIVER', null, 'en'));

        $result = $this->login('new@example.com', 'correct horse');

        self::assertSame('email-not-verified', $result->failure?->problemSlug());
        self::assertSame(403, $result->failure->httpStatus());
        self::assertSame('email-not-verified', $this->world->eventBus->of(LoginFailedV1::class)[0]->reason);
        self::assertSame([], $this->world->refreshTokens->all());
    }

    public function testABannedAccountCannotLogIn(): void
    {
        $this->world->verifiedAccount()->ban();

        $result = $this->login();

        self::assertSame('account-banned', $result->failure?->problemSlug());
    }

    public function testAnAdminGetsOnlyASecondFactorPendingToken(): void
    {
        $admin = $this->world->verifiedAccount('root@example.com', AccountRole::Admin);

        $result = $this->login('root@example.com');

        self::assertTrue($result->isTwoFactorRequired());
        self::assertFalse($result->isSuccess());
        self::assertNull($result->tokens);
        self::assertSame(sprintf('access|%s||pending_2fa', $admin->id()->toString()), $result->pendingAccessToken);
        self::assertSame(900, $result->pendingExpiresInSeconds);
        self::assertSame([], $this->world->refreshTokens->all());
        self::assertSame([], $this->world->eventBus->of(UserLoggedInV1::class));
    }

    public function testAnOldHashIsUpgradedOnLogin(): void
    {
        $account = $this->world->verifiedAccount();
        $weak = new \App\Authentication\Infrastructure\Crypto\Argon2idPasswordHasher(8, 1);
        $strongerWorldHasher = new \App\Authentication\Infrastructure\Crypto\Argon2idPasswordHasher(16, 1);
        $loginHandler = new LoginHandler($this->world->accounts, $strongerWorldHasher, $this->world->sessions, $this->world->events);
        self::assertTrue($weak->verify(new \App\Authentication\Domain\Model\PlainPassword(AuthenticationWorld::PASSWORD), $account->passwordHash() ?? throw new LogicException()));

        $loginHandler(new Login('ana@example.com', AuthenticationWorld::PASSWORD, $this->context));

        self::assertFalse($strongerWorldHasher->needsRehash($account->passwordHash()));
        self::assertNull($account->passwordChangedAt());
        self::assertTrue($strongerWorldHasher->verify(new \App\Authentication\Domain\Model\PlainPassword(AuthenticationWorld::PASSWORD), $account->passwordHash()));
    }

    public function testRefreshingRotatesTheTokenAndIssuesANewAccessToken(): void
    {
        $this->world->verifiedAccount();
        $first = $this->login()->tokens ?? throw new LogicException();
        $this->world->clock->advance('+10 minutes');

        $second = $this->refresh($first->refreshToken)->tokens ?? throw new LogicException();

        self::assertNotSame($first->refreshToken, $second->refreshToken);
        self::assertSame(900, $second->expiresInSeconds);
        $old = $this->world->refreshTokens->findByHash($this->world->tokenHasher->hash($first->refreshToken));
        self::assertSame(RefreshTokenStatus::Rotated, $old?->status($this->world->clock->now()));
        $new = $this->world->refreshTokens->findByHash($this->world->tokenHasher->hash($second->refreshToken));
        self::assertNotNull($new);
        self::assertSame($old->familyId()->toString(), $new->familyId()->toString());
        self::assertEquals($this->world->clock->now()->modify('+30 days'), $new->expiresAt());
    }

    public function testRefreshingAnUnknownTokenIsRefused(): void
    {
        $result = $this->refresh('0123456789012345678901234567890');

        self::assertSame('token-invalid', $result->failure?->problemSlug());
    }

    public function testReusingARotatedTokenRevokesTheWholeFamilyAndPublishesAnEvent(): void
    {
        $account = $this->world->verifiedAccount();
        $first = $this->login()->tokens ?? throw new LogicException();
        $second = $this->refresh($first->refreshToken)->tokens ?? throw new LogicException();

        $reuse = $this->refresh($first->refreshToken);

        self::assertSame('token-revoked', $reuse->failure?->problemSlug());
        $events = $this->world->eventBus->of(RefreshTokenReuseDetectedV1::class);
        self::assertCount(1, $events);
        self::assertSame($account->id()->toString(), $events[0]->accountId->toString());
        self::assertSame('203.0.113.7', $events[0]->ip);
        // The legitimate holder of the newest token is cut off as well
        self::assertSame('token-revoked', $this->refresh($second->refreshToken)->failure?->problemSlug());
    }

    public function testReuseDoesNotAffectOtherFamilies(): void
    {
        $this->world->verifiedAccount();
        $phone = $this->login()->tokens ?? throw new LogicException();
        $laptop = $this->login()->tokens ?? throw new LogicException();
        $this->refresh($phone->refreshToken);

        $this->refresh($phone->refreshToken);

        self::assertTrue($this->refresh($laptop->refreshToken)->isSuccess());
    }

    public function testAnExpiredRefreshTokenIsRefusedWithoutSideEffects(): void
    {
        $this->world->verifiedAccount();
        $tokens = $this->login()->tokens ?? throw new LogicException();
        $this->world->clock->advance('+30 days -1 second');
        self::assertTrue($this->refresh($tokens->refreshToken)->isSuccess());

        $this->world->clock->advance('+30 days');
        $expired = $this->refresh($tokens->refreshToken);

        self::assertSame('token-revoked', $expired->failure?->problemSlug(), 'the first token was already rotated');
    }

    public function testARefreshTokenExpiresExactlyAtItsExpiry(): void
    {
        $this->world->verifiedAccount();
        $tokens = $this->login()->tokens ?? throw new LogicException();
        $this->world->clock->advance('+30 days');

        $result = $this->refresh($tokens->refreshToken);

        self::assertSame('token-expired', $result->failure?->problemSlug());
        self::assertSame([], $this->world->eventBus->of(RefreshTokenReuseDetectedV1::class));
    }

    public function testARevokedTokenCannotBeRefreshed(): void
    {
        $this->world->verifiedAccount();
        $tokens = $this->login()->tokens ?? throw new LogicException();
        ($this->world->logout())(new Logout($tokens->refreshToken));

        $result = $this->refresh($tokens->refreshToken);

        self::assertSame('token-revoked', $result->failure?->problemSlug());
        self::assertSame([], $this->world->eventBus->of(RefreshTokenReuseDetectedV1::class), 'a logged-out token is not a stolen one');
    }

    public function testRefreshingForABannedAccountRevokesTheFamily(): void
    {
        $account = $this->world->verifiedAccount();
        $tokens = $this->login()->tokens ?? throw new LogicException();
        $account->ban();

        $result = $this->refresh($tokens->refreshToken);

        self::assertSame('account-banned', $result->failure?->problemSlug());
        $stored = $this->world->refreshTokens->findByHash($this->world->tokenHasher->hash($tokens->refreshToken));
        self::assertSame(RefreshTokenStatus::Revoked, $stored?->status($this->world->clock->now()));
    }

    public function testRefreshingForAVanishedAccountIsRefused(): void
    {
        $this->world->verifiedAccount();
        $tokens = $this->login()->tokens ?? throw new LogicException();
        $emptyAccounts = new \App\Authentication\Infrastructure\Persistence\InMemoryUserAccountRepository($this->world->collector);
        $handler = new RefreshTokensHandler($this->world->refreshTokens, $emptyAccounts, $this->world->tokenHasher, $this->world->sessions, $this->world->events, $this->world->clock);

        $result = $handler(new RefreshTokens($tokens->refreshToken, $this->context));

        self::assertSame('token-invalid', $result->failure?->problemSlug());
    }

    public function testAnAdminFamilyKeepsTheSecondFactorMethodWhenRefreshed(): void
    {
        $admin = $this->world->verifiedAccount('root@example.com', AccountRole::Admin);
        $tokens = $this->world->sessions->start($admin, AuthenticationMethod::PasswordAndOtp);

        $refreshed = $this->refresh($tokens->refreshToken)->tokens ?? throw new LogicException();

        self::assertStringEndsWith('|ROLE_ADMIN|pwd+otp', $refreshed->accessToken);
    }

    public function testLoggingOutRevokesTheFamilyAndPublishesAnEvent(): void
    {
        $account = $this->world->verifiedAccount();
        $phone = $this->login()->tokens ?? throw new LogicException();
        $laptop = $this->login()->tokens ?? throw new LogicException();

        ($this->world->logout())(new Logout($phone->refreshToken));

        self::assertSame('token-revoked', $this->refresh($phone->refreshToken)->failure?->problemSlug());
        self::assertTrue($this->refresh($laptop->refreshToken)->isSuccess());
        $events = $this->world->eventBus->of(UserLoggedOutV1::class);
        self::assertCount(1, $events);
        self::assertSame($account->id()->toString(), $events[0]->accountId->toString());
        self::assertFalse($events[0]->allDevices);
    }

    public function testLoggingOutOfAllDevicesRevokesEveryFamily(): void
    {
        $this->world->verifiedAccount();
        $phone = $this->login()->tokens ?? throw new LogicException();
        $laptop = $this->login()->tokens ?? throw new LogicException();

        ($this->world->logout())(new Logout($phone->refreshToken, true));

        self::assertSame('token-revoked', $this->refresh($laptop->refreshToken)->failure?->problemSlug());
        self::assertTrue($this->world->eventBus->of(UserLoggedOutV1::class)[0]->allDevices);
    }

    public function testLoggingOutTwiceOrWithAnUnknownTokenIsHarmless(): void
    {
        $this->world->verifiedAccount();
        $tokens = $this->login()->tokens ?? throw new LogicException();

        ($this->world->logout())(new Logout($tokens->refreshToken));
        ($this->world->logout())(new Logout($tokens->refreshToken));
        ($this->world->logout())(new Logout('0123456789012345678901234567890'));

        self::assertCount(2, $this->world->eventBus->of(UserLoggedOutV1::class));
    }

    public function testRevokingEverySessionOfAnAccount(): void
    {
        $account = $this->world->verifiedAccount();
        $other = $this->world->verifiedAccount('bob@example.com');
        $mine = $this->login()->tokens ?? throw new LogicException();
        $theirs = $this->login('bob@example.com')->tokens ?? throw new LogicException();

        ($this->world->revokeAll())(new RevokeAllRefreshTokens($account->id()->toString()));

        self::assertSame('token-revoked', $this->refresh($mine->refreshToken)->failure?->problemSlug());
        self::assertTrue($this->refresh($theirs->refreshToken)->isSuccess());
    }

    public function testBanningMarksTheAccountAndEndsItsSessionsAndIsIdempotent(): void
    {
        $account = $this->world->verifiedAccount();
        $tokens = $this->login()->tokens ?? throw new LogicException();

        ($this->world->ban())(new BanAccount($account->id()->toString()));
        ($this->world->ban())(new BanAccount($account->id()->toString()));

        self::assertTrue($account->isBanned());
        self::assertSame('token-revoked', $this->refresh($tokens->refreshToken)->failure?->problemSlug());
    }

    public function testBanningAnUnknownAccountIsIgnored(): void
    {
        ($this->world->ban())(new BanAccount('01900000-0000-7000-8000-00000000ffff'));

        $this->addToAssertionCount(1);
    }

    public function testTheSessionFactoryFallsBackToTheRegisteredRole(): void
    {
        $account = $this->world->verifiedAccount('tow@example.com', AccountRole::Tower);

        self::assertSame(['ROLE_TOWER'], $this->world->sessions->securityRoles($account));
    }
}
