<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Command\Result\TwoFactorEnrolment;
use App\Authentication\Application\Service\TwoFactorEnroller;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandHandler;

final readonly class StartTwoFactorEnrolmentHandler implements CommandHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private TwoFactorEnroller $enroller,
    ) {
    }

    public function __invoke(StartTwoFactorEnrolment $command): TwoFactorEnrolment
    {
        $account = $this->accounts->findById(new AccountId($command->accountId));
        if (null === $account) {
            throw AuthenticationProblem::tokenInvalid();
        }
        if (AccountRole::Admin !== $account->role()) {
            throw AuthenticationProblem::adminOnly();
        }

        return $this->enroller->start($account);
    }
}
