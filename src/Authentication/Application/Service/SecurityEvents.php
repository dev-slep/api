<?php

declare(strict_types=1);

namespace App\Authentication\Application\Service;

use App\Authentication\Application\Command\RequestContext;
use App\Authentication\Contract\Event\LoginFailedV1;
use App\Authentication\Contract\Event\PasswordResetRequestedV1;
use App\Authentication\Contract\Event\RefreshTokenReuseDetectedV1;
use App\Authentication\Contract\Event\TwoFactorFailedV1;
use App\Authentication\Contract\Event\TwoFactorVerifiedV1;
use App\Authentication\Contract\Event\UserLoggedInV1;
use App\Authentication\Contract\Event\UserLoggedOutV1;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\SharedKernel\Application\EventBus;
use App\SharedKernel\Contract\UserId;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;

/**
 * Publishes the security-relevant events that don't come from an aggregate's own state change (successful and
 * failed logins, logouts, token reuse, second-factor attempts). They are published inside the command's
 * transaction, so they are stored together with the command's other changes (transactional outbox).
 */
final readonly class SecurityEvents
{
    public function __construct(
        private EventBus $eventBus,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function loggedIn(AccountId $accountId, string $method, RequestContext $context): void
    {
        $this->eventBus->publish(new UserLoggedInV1($this->ids->generate(), $this->clock->now(), $this->user($accountId), $method, $context->ip, $context->userAgent));
    }

    public function loginFailed(?AccountId $accountId, string $reason, RequestContext $context): void
    {
        $this->eventBus->publish(new LoginFailedV1($this->ids->generate(), $this->clock->now(), null === $accountId ? null : $this->user($accountId), $reason, $context->ip, $context->userAgent));
    }

    public function loggedOut(AccountId $accountId, bool $allDevices): void
    {
        $this->eventBus->publish(new UserLoggedOutV1($this->ids->generate(), $this->clock->now(), $this->user($accountId), $allDevices));
    }

    public function refreshTokenReused(AccountId $accountId, TokenFamilyId $familyId, RequestContext $context): void
    {
        $this->eventBus->publish(new RefreshTokenReuseDetectedV1($this->ids->generate(), $this->clock->now(), $this->user($accountId), $familyId->toString(), $context->ip, $context->userAgent));
    }

    public function passwordResetRequested(AccountId $accountId, RequestContext $context): void
    {
        $this->eventBus->publish(new PasswordResetRequestedV1($this->ids->generate(), $this->clock->now(), $this->user($accountId), $context->ip));
    }

    public function twoFactorVerified(AccountId $accountId, string $method, RequestContext $context): void
    {
        $this->eventBus->publish(new TwoFactorVerifiedV1($this->ids->generate(), $this->clock->now(), $this->user($accountId), $method, $context->ip, $context->userAgent));
    }

    public function twoFactorFailed(AccountId $accountId, RequestContext $context): void
    {
        $this->eventBus->publish(new TwoFactorFailedV1($this->ids->generate(), $this->clock->now(), $this->user($accountId), $context->ip, $context->userAgent));
    }

    private function user(AccountId $accountId): UserId
    {
        return new UserId($accountId->toString());
    }
}
