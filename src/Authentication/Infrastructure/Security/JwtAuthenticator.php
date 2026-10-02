<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Security;

use App\Authentication\Application\Port\AccessTokenVerifier;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\SharedKernel\Infrastructure\Http\ProblemDetailsFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates a request from its `Authorization: Bearer <access token>` header. A request without the header
 * stays anonymous (the access rules decide whether that is allowed); a bad token is rejected with a 401 problem.
 */
final class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AccessTokenVerifier $verifier,
        private readonly ProblemDetailsFactory $problems,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->bearerToken($request);
        if (null === $token) {
            throw new TokenAuthenticationException(AuthenticationProblem::tokenInvalid());
        }

        try {
            $principal = $this->verifier->verify($token);
        } catch (AuthenticationProblem $problem) {
            throw new TokenAuthenticationException($problem);
        }

        return new SelfValidatingPassport(new UserBadge($principal->accountId, static fn (): AuthenticatedUser => new AuthenticatedUser($principal)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $failure = $exception instanceof TokenAuthenticationException ? $exception->problem : $exception;
        $response = $this->problems->create($failure, $request->getPathInfo(), $request->getLocale());
        $response->headers->set('WWW-Authenticate', 'Bearer');

        return $response;
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization', '');
        if (1 !== preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
