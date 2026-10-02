<?php

declare(strict_types=1);

namespace App\Tests\Application\Authentication;

use App\Authentication\Application\Command\BanAccount;
use App\Authentication\Application\Command\RegisterUser;
use App\Authentication\Application\Command\VerifyEmail;
use App\Authentication\Application\ContractImplementation\AccountLookupFacade;
use App\Authentication\Contract\AccountLookup;
use App\Authentication\Contract\Dto\AccountView;
use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Contract\UserId;
use App\Tests\Support\Attribute\CoversContractMethod;
use App\Tests\Support\Authentication\AuthenticationApplicationTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AccountLookupFacade::class)]
#[CoversContractMethod(AccountLookup::class, 'findByEmail')]
#[CoversContractMethod(AccountLookup::class, 'findById')]
final class AccountLookupContractTest extends AuthenticationApplicationTestCase
{
    private function lookup(): AccountLookup
    {
        $lookup = static::getContainer()->get(AccountLookup::class);

        return $lookup;
    }

    private function bus(): CommandBus
    {
        $bus = static::getContainer()->get(CommandBus::class);

        return $bus;
    }

    private function register(string $email = 'ana@example.com', string $role = 'TOWER'): void
    {
        $this->bus()->dispatch(new RegisterUser($email, 'correct horse battery', $role, '+381641234567', 'sr_Latn'));
    }

    private function idOf(string $email): UserId
    {
        return $this->lookup()->findByEmail($email)->id ?? throw new LogicException('The account does not exist.');
    }

    public function testFindingByEmailReturnsTheViewWithAllFields(): void
    {
        $this->register();

        $view = $this->lookup()->findByEmail('Ana@Example.com');

        self::assertInstanceOf(AccountView::class, $view);
        self::assertSame('ana@example.com', $view->email);
        self::assertSame('TOWER', $view->role);
        self::assertSame('+381641234567', $view->phone);
        self::assertSame('sr_Latn', $view->locale);
        self::assertFalse($view->emailVerified);
        self::assertFalse($view->banned);
    }

    public function testFindingByEmailTreatsUnknownAndMalformedEmailsAsAbsent(): void
    {
        $this->register();

        self::assertNull($this->lookup()->findByEmail('nobody@example.com'));
        self::assertNull($this->lookup()->findByEmail('not an email'));
        self::assertNull($this->lookup()->findByEmail(''));
    }

    public function testFindingByIdReturnsTheSameView(): void
    {
        $this->register();
        $byEmail = $this->lookup()->findByEmail('ana@example.com');

        $byId = $this->lookup()->findById($byEmail->id ?? throw new LogicException());

        self::assertEquals($byEmail, $byId);
    }

    public function testFindingAnUnknownIdReturnsNull(): void
    {
        $this->register();

        self::assertNull($this->lookup()->findById(new UserId('01900000-0000-7000-8000-00000000ffff')));
    }

    public function testTheViewFollowsTheStateOfTheAccount(): void
    {
        $this->register();
        $id = $this->idOf('ana@example.com');

        $this->bus()->dispatch(new VerifyEmail($this->mailer->lastToken('verification')));
        self::assertTrue($this->lookup()->findById($id)?->emailVerified);

        $this->bus()->dispatch(new BanAccount($id->toString()));
        self::assertTrue($this->lookup()->findById($id)->banned);
    }

    public function testAccountsDoNotLeakIntoEachOther(): void
    {
        $this->register('ana@example.com', 'DRIVER');
        $this->register('bob@example.com', 'TOWER');

        self::assertSame('DRIVER', $this->lookup()->findByEmail('ana@example.com')?->role);
        self::assertSame('TOWER', $this->lookup()->findByEmail('bob@example.com')?->role);
        self::assertFalse($this->idOf('ana@example.com')->equals($this->idOf('bob@example.com')));
    }
}
