<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Security;

use App\SharedKernel\Infrastructure\Http\ProblemDetailsFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Answers anonymous requests to protected routes with 401 and authenticated requests that lack the right with 403,
 * both as problem details.
 */
final readonly class ProblemEntryPoint implements AuthenticationEntryPointInterface, AccessDeniedHandlerInterface
{
    public function __construct(private ProblemDetailsFactory $problems)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $response = $this->problems->create($authException ?? new AuthenticationException(), $request->getPathInfo(), $request->getLocale());
        $response->headers->set('WWW-Authenticate', 'Bearer');

        return $response;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return $this->problems->create($accessDeniedException, $request->getPathInfo(), $request->getLocale());
    }
}
