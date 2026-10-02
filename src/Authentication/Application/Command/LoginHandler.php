<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Application\Service\SecurityEvents;
use App\Authentication\Application\Service\SessionFactory;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Exception\InvalidValue;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Domain\Policy\PasswordHasher;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandHandler;

final readonly class LoginHandler implements CommandHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private PasswordHasher $hasher,
        private SessionFactory $sessions,
        private SecurityEvents $events,
    ) {
    }

    public function __invoke(Login $command): AuthenticationResult
    {
        try {
            $email = new Email($command->email);
            $password = new PlainPassword($command->password);
        } catch (InvalidValue) {
            $this->events->loginFailed(null, 'invalid_credentials', $command->context);

            return AuthenticationResult::failed(AuthenticationProblem::invalidCredentials());
        }

        $account = $this->accounts->findByEmail($email);
        if (null === $account || !$account->hasPassword()) {
            // The hash is thrown away: it only spends the same time as a real check, so the response time
            // does not reveal whether the email is registered.
            $this->hasher->hash($password);
            $this->events->loginFailed($account?->id(), 'invalid_credentials', $command->context);

            return AuthenticationResult::failed(AuthenticationProblem::invalidCredentials());
        }

        $hash = $account->passwordHash();
        if (null === $hash || !$this->hasher->verify($password, $hash)) {
            $this->events->loginFailed($account->id(), 'invalid_credentials', $command->context);

            return AuthenticationResult::failed(AuthenticationProblem::invalidCredentials());
        }

        if ($this->hasher->needsRehash($hash)) {
            $account->upgradePasswordHash($this->hasher->hash($password));
            $this->accounts->save($account);
        }

        try {
            $account->assertCanLogIn();
        } catch (AuthenticationProblem $refusal) {
            $this->events->loginFailed($account->id(), $refusal->problemSlug(), $command->context);

            return AuthenticationResult::failed($refusal);
        }

        if (AccountRole::Admin === $account->role()) {
            return $this->sessions->pendingTwoFactor($account);
        }

        $tokens = $this->sessions->start($account, AuthenticationMethod::Password);
        $this->events->loggedIn($account->id(), AuthenticationMethod::Password->value, $command->context);

        return AuthenticationResult::success($tokens);
    }
}
