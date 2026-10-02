<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Repository;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\UserAccount;

interface UserAccountRepository
{
    public function findById(AccountId $id): ?UserAccount;

    public function findByEmail(Email $email): ?UserAccount;

    public function findBySocialIdentity(SocialIdentity $identity): ?UserAccount;

    /**
     * Saves the account and registers it for event publishing.
     *
     * @throws AuthenticationProblem email-already-registered when another account has the email
     */
    public function save(UserAccount $account): void;
}
