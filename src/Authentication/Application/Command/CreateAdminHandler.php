<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Command\Result\CreatedAdmin;
use App\Authentication\Application\Service\TwoFactorEnroller;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Policy\PasswordHasher;
use App\Authentication\Domain\Policy\PasswordPolicy;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;

final readonly class CreateAdminHandler implements CommandHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private PasswordHasher $hasher,
        private PasswordPolicy $passwordPolicy,
        private TwoFactorEnroller $enroller,
        private Clock $clock,
        private IdGenerator $ids,
        private string $defaultLocale = 'sr_Latn',
    ) {
    }

    public function __invoke(CreateAdmin $command): CreatedAdmin
    {
        $email = new Email($command->email);
        $password = new PlainPassword($command->password);
        $this->passwordPolicy->assertAcceptable($password, $email);

        if (null !== $this->accounts->findByEmail($email)) {
            throw AuthenticationProblem::emailAlreadyRegistered();
        }

        $account = UserAccount::createAdmin(new AccountId($this->ids->generate()), $email, $this->hasher->hash($password), new Locale($this->defaultLocale), $this->clock->now());
        $this->accounts->save($account);

        return new CreatedAdmin($account->id()->toString(), $this->enroller->start($account));
    }
}
