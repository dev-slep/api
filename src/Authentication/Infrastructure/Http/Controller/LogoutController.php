<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\Logout;
use App\Authentication\Infrastructure\Http\Request\LogoutBody;
use App\SharedKernel\Application\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class LogoutController
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[OA\Post(summary: 'Revoke a refresh token family', description: 'Idempotent: an unknown or already revoked token is ignored. `allDevices` revokes every session of the account.', security: [])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Response(response: 204, description: 'Logged out.')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[Route('/api/v1/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] LogoutBody $body): Response
    {
        $this->commandBus->dispatch(new Logout($body->refreshToken, $body->allDevices));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
