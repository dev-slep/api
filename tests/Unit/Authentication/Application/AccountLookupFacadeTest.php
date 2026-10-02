<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Application;

use App\Authentication\Application\ContractImplementation\AccountLookupFacade;
use App\Authentication\Domain\Model\AccountRole;
use App\SharedKernel\Contract\UserId;
use App\Tests\Support\Authentication\AuthenticationWorld;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountLookupFacade::class)]
final class AccountLookupFacadeTest extends TestCase
{
    public function testFindingByIdReturnsAFullView(): void
    {
        $world = new AuthenticationWorld();
        $account = $world->verifiedAccount('ana@example.com', AccountRole::Tower);
        $facade = new AccountLookupFacade($world->accounts);

        $view = $facade->findById(new UserId($account->id()->toString()));

        self::assertNotNull($view);
        self::assertSame($account->id()->toString(), $view->id->toString());
        self::assertSame('ana@example.com', $view->email);
        self::assertSame('TOWER', $view->role);
        self::assertNull($view->phone);
        self::assertSame('en', $view->locale);
        self::assertTrue($view->emailVerified);
        self::assertFalse($view->banned);
    }

    public function testBannedAccountsAreMarked(): void
    {
        $world = new AuthenticationWorld();
        $world->verifiedAccount()->ban();

        self::assertTrue((new AccountLookupFacade($world->accounts))->findByEmail('ana@example.com')?->banned);
    }

    public function testFindingByEmailIsCaseInsensitiveAndTolerantOfGarbage(): void
    {
        $world = new AuthenticationWorld();
        $world->verifiedAccount('ana@example.com');
        $facade = new AccountLookupFacade($world->accounts);

        self::assertNotNull($facade->findByEmail(' ANA@example.com '));
        self::assertNull($facade->findByEmail('nobody@example.com'));
        self::assertNull($facade->findByEmail('not an email'));
        self::assertNull($facade->findByEmail(''));
        self::assertNull($facade->findById(new UserId('01900000-0000-7000-8000-00000000ffff')));
    }
}
