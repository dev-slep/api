<?php

declare(strict_types=1);

namespace App\Tests\Application\Authentication;

use App\Authentication\Application\Port\AuthenticatedPrincipal;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Contract\CurrentUser;
use App\Authentication\Infrastructure\Security\AuthenticatedUser;
use App\Authentication\Infrastructure\Security\SecurityCurrentUser;
use App\Tests\Support\ApplicationTestCase;
use App\Tests\Support\Attribute\CoversContractMethod;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

#[CoversClass(SecurityCurrentUser::class)]
#[CoversContractMethod(CurrentUser::class, 'isAuthenticated')]
#[CoversContractMethod(CurrentUser::class, 'id')]
#[CoversContractMethod(CurrentUser::class, 'roles')]
#[CoversContractMethod(CurrentUser::class, 'isTwoFactorVerified')]
final class CurrentUserContractTest extends ApplicationTestCase
{
    private const string ACCOUNT = '01900000-0000-7000-8000-0000000000aa';

    private function currentUser(): CurrentUser
    {
        $currentUser = static::getContainer()->get(CurrentUser::class);

        return $currentUser;
    }

    /**
     * @param list<string> $roles
     */
    private function signIn(AuthenticationMethod $method, array $roles): void
    {
        $user = new AuthenticatedUser(new AuthenticatedPrincipal(self::ACCOUNT, $roles, $method));
        $storage = static::getContainer()->get('security.token_storage');
        $storage->setToken(new UsernamePasswordToken($user, 'api', $roles));
    }

    public function testNobodyIsAuthenticatedWithoutAToken(): void
    {
        self::assertFalse($this->currentUser()->isAuthenticated());
    }

    public function testTheAuthenticatedUserIsTheOneOfTheToken(): void
    {
        $this->signIn(AuthenticationMethod::Password, ['ROLE_DRIVER']);

        self::assertTrue($this->currentUser()->isAuthenticated());
        self::assertSame(self::ACCOUNT, $this->currentUser()->id()->toString());
    }

    public function testAskingForTheIdOfNobodyFails(): void
    {
        $this->expectException(LogicException::class);

        $this->currentUser()->id();
    }

    public function testTheRolesComeFromTheToken(): void
    {
        self::assertSame([], $this->currentUser()->roles());

        $this->signIn(AuthenticationMethod::Password, ['ROLE_DRIVER', 'ROLE_TOWER']);

        self::assertSame(['ROLE_DRIVER', 'ROLE_TOWER'], $this->currentUser()->roles());
    }

    public function testOnlyATokenIssuedAfterTheSecondFactorIsTwoFactorVerified(): void
    {
        self::assertFalse($this->currentUser()->isTwoFactorVerified());

        $this->signIn(AuthenticationMethod::PendingTwoFactor, []);
        self::assertFalse($this->currentUser()->isTwoFactorVerified());

        $this->signIn(AuthenticationMethod::Password, ['ROLE_DRIVER']);
        self::assertFalse($this->currentUser()->isTwoFactorVerified());

        $this->signIn(AuthenticationMethod::PasswordAndOtp, ['ROLE_ADMIN']);
        self::assertTrue($this->currentUser()->isTwoFactorVerified());
    }
}
