<?php

declare(strict_types=1);

namespace App\Authentication\Application\ContractImplementation;

use App\Authentication\Contract\AccountLookup;
use App\Authentication\Contract\Dto\AccountView;
use App\Authentication\Domain\Exception\InvalidValue;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Contract\UserId;

final readonly class AccountLookupFacade implements AccountLookup
{
    public function __construct(private UserAccountRepository $accounts)
    {
    }

    public function findById(UserId $id): ?AccountView
    {
        $account = $this->accounts->findById(new AccountId($id->toString()));

        return null === $account ? null : $this->view($account);
    }

    public function findByEmail(string $email): ?AccountView
    {
        try {
            $account = $this->accounts->findByEmail(new Email($email));
        } catch (InvalidValue) {
            return null;
        }

        return null === $account ? null : $this->view($account);
    }

    private function view(UserAccount $account): AccountView
    {
        return new AccountView(
            new UserId($account->id()->toString()),
            $account->email()->toString(),
            $account->role()->value,
            $account->phone()?->toString(),
            $account->locale()->toString(),
            $account->isEmailVerified(),
            $account->isBanned(),
        );
    }
}
