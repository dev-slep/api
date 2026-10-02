<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http;

use App\Authentication\Application\Command\RequestContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Takes the client address and user agent of a request, for the security events.
 */
final readonly class RequestContextFactory
{
    private const int MAX_USER_AGENT_LENGTH = 255;

    public function fromRequest(Request $request): RequestContext
    {
        $userAgent = $request->headers->get('User-Agent');

        return new RequestContext(
            $request->getClientIp(),
            null === $userAgent ? null : mb_substr($userAgent, 0, self::MAX_USER_AGENT_LENGTH),
        );
    }
}
