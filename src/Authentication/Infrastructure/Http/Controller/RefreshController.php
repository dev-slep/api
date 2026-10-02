<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\RefreshTokens;
use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Infrastructure\Http\Request\RefreshBody;
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
final readonly class RefreshController
{
    public function __construct(
        private CommandBus $commandBus,
        private RequestContextFactory $contexts,
    ) {
    }

    #[OA\Post(summary: 'Exchange a refresh token for new tokens', description: 'The refresh token is single-use. Presenting one that was already used revokes the whole session family.', security: [])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Response(response: 200, description: 'New tokens.', content: new OA\JsonContent(ref: new Model(type: TokenResponse::class)))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[Route('/api/v1/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function __invoke(Request $request, #[MapRequestPayload] RefreshBody $body): JsonResponse
    {
        $result = $this->commandBus->dispatch(new RefreshTokens($body->refreshToken, $this->contexts->fromRequest($request)));
        assert($result instanceof AuthenticationResult);

        return AuthenticationResponses::from($result);
    }
}
