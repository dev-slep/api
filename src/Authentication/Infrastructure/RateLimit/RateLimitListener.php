<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\RateLimit;

use App\Authentication\Contract\CurrentUser;

use function is_array;
use function is_string;

use const JSON_THROW_ON_ERROR;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Throwable;

/**
 * Brute-force protection (backend-specification §7.1): limits login, registration, password reset, email
 * verification, social sign-in and second-factor attempts per client address and, where the request names one, per
 * email address or account. Runs after the firewall, so the second-factor limit can use the account of the token.
 */
final readonly class RateLimitListener
{
    public function __construct(
        private RateLimiterFactoryInterface $authLoginIpLimiter,
        private RateLimiterFactoryInterface $authLoginEmailLimiter,
        private RateLimiterFactoryInterface $authRegisterIpLimiter,
        private RateLimiterFactoryInterface $authPasswordIpLimiter,
        private RateLimiterFactoryInterface $authPasswordEmailLimiter,
        private RateLimiterFactoryInterface $authEmailIpLimiter,
        private RateLimiterFactoryInterface $authEmailEmailLimiter,
        private RateLimiterFactoryInterface $authSocialIpLimiter,
        private RateLimiterFactoryInterface $authTokenIpLimiter,
        private RateLimiterFactoryInterface $authTwoFactorAccountLimiter,
        private RateLimiterFactoryInterface $authTwoFactorIpLimiter,
        private CurrentUser $currentUser,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST')) {
            return;
        }

        $ip = $request->getClientIp() ?? 'unknown';

        match ($request->attributes->get('_route')) {
            'auth_login' => $this->consume([[$this->authLoginIpLimiter, $ip], [$this->authLoginEmailLimiter, $this->email($request)]]),
            'auth_register' => $this->consume([[$this->authRegisterIpLimiter, $ip]]),
            'auth_password_forgot' => $this->consume([[$this->authPasswordIpLimiter, $ip], [$this->authPasswordEmailLimiter, $this->email($request)]]),
            'auth_email_resend' => $this->consume([[$this->authEmailIpLimiter, $ip], [$this->authEmailEmailLimiter, $this->email($request)]]),
            'auth_social_login' => $this->consume([[$this->authSocialIpLimiter, $ip]]),
            'auth_password_reset', 'auth_email_verify' => $this->consume([[$this->authTokenIpLimiter, $ip]]),
            'admin_auth_2fa_verify' => $this->consume([[$this->authTwoFactorIpLimiter, $ip], [$this->authTwoFactorAccountLimiter, $this->account()]]),
            default => null,
        };
    }

    /**
     * @param list<array{0: RateLimiterFactoryInterface, 1: string|null}> $checks
     */
    private function consume(array $checks): void
    {
        foreach ($checks as [$factory, $key]) {
            if (null === $key) {
                continue;
            }

            $limit = $factory->create($key)->consume();
            if (!$limit->isAccepted()) {
                $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

                throw new TooManyRequestsHttpException($retryAfter, 'Too many attempts. Try again later.');
            }
        }
    }

    private function email(Request $request): ?string
    {
        try {
            $body = json_decode($request->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $email = is_array($body) ? ($body['email'] ?? null) : null;

        return is_string($email) && '' !== trim($email) ? mb_strtolower(trim($email)) : null;
    }

    private function account(): ?string
    {
        return $this->currentUser->isAuthenticated() ? $this->currentUser->id()->toString() : null;
    }
}
