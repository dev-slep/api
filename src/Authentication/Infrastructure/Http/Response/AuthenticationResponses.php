<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Response;

use App\Authentication\Application\Command\Result\AuthenticationResult;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Turns the outcome of a login-like command into its JSON response (a refusal throws its problem).
 */
final readonly class AuthenticationResponses
{
    public static function from(AuthenticationResult $result): JsonResponse
    {
        $result->orThrow();

        if (null !== $result->tokens) {
            return new JsonResponse(new TokenResponse($result->tokens->accessToken, $result->tokens->refreshToken, $result->tokens->expiresInSeconds));
        }

        return new JsonResponse(new TwoFactorRequiredResponse((string) $result->pendingAccessToken, (int) $result->pendingExpiresInSeconds, true));
    }
}
