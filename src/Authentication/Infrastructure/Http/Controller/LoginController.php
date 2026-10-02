<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\Login;
use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Infrastructure\Http\Request\LoginBody;
use App\Authentication\Infrastructure\Http\RequestContextFactory;
use App\Authentication\Infrastructure\Http\Response\AuthenticationResponses;
use App\Authentication\Infrastructure\Http\Response\TokenResponse;
use App\Authentication\Infrastructure\Http\Response\TwoFactorRequiredResponse;
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
final readonly class LoginController
{
    public function __construct(
        private CommandBus $commandBus,
        private RequestContextFactory $contexts,
    ) {
    }

    #[OA\Post(summary: 'Log in with email and password', description: 'Returns access and refresh tokens. Admins get a token that only opens the two-factor endpoints (`twoFactorRequired: true`). The email must be verified.', security: [])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Response(response: 200, description: 'Tokens, or a two-factor-pending token for admins.', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: new Model(type: TokenResponse::class)), new OA\Schema(ref: new Model(type: TwoFactorRequiredResponse::class))]))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    #[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    #[Route('/api/v1/auth/login', name: 'auth_login', methods: ['POST'])]
    public function __invoke(Request $request, #[MapRequestPayload] LoginBody $body): JsonResponse
    {
        $result = $this->commandBus->dispatch(new Login($body->email, $body->password, $this->contexts->fromRequest($request)));
        assert($result instanceof AuthenticationResult);

        return AuthenticationResponses::from($result);
    }
}
