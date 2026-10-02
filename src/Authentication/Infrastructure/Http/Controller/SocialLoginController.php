<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Application\Command\SocialLogin;
use App\Authentication\Infrastructure\Http\Request\SocialLoginBody;
use App\Authentication\Infrastructure\Http\RequestContextFactory;
use App\Authentication\Infrastructure\Http\Response\AuthenticationResponses;
use App\Authentication\Infrastructure\Http\Response\TokenResponse;
use App\SharedKernel\Application\CommandBus;

use function assert;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SocialLoginController
{
    public function __construct(
        private CommandBus $commandBus,
        private RequestContextFactory $contexts,
    ) {
    }

    #[OA\Post(summary: 'Log in with a Google or Apple ID token', description: 'Creates the account on first use (a role is required then) or links the identity to an account with the same provider-confirmed email. The client generates a random `nonce`, passes its SHA-256 hex digest to the provider when signing in, and sends the raw value here.', security: [])]
    #[OA\Parameter(name: 'provider', in: 'path', required: true, description: 'The sign-in provider.', style: 'simple', explode: false, schema: new OA\Schema(type: 'string', enum: ['google', 'apple']))]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Response(response: 200, description: 'Tokens.', content: new OA\JsonContent(ref: new Model(type: TokenResponse::class)))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    #[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
    #[OA\Response(response: 409, ref: '#/components/responses/Conflict')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    #[OA\Response(response: 503, ref: '#/components/responses/ServiceUnavailable')]
    #[Route('/api/v1/auth/social/{provider}', name: 'auth_social_login', requirements: ['provider' => 'google|apple'], methods: ['POST'])]
    public function __invoke(Request $request, string $provider, #[MapRequestPayload] SocialLoginBody $body): JsonResponse
    {
        $result = $this->commandBus->dispatch(new SocialLogin(
            $provider,
            $body->idToken,
            $body->role,
            $body->phone,
            $body->locale ?? $request->getLocale(),
            $this->contexts->fromRequest($request),
            $body->nonce,
        ));
        assert($result instanceof AuthenticationResult);

        return AuthenticationResponses::from($result);
    }
}
