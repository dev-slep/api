<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\ResetPassword;
use App\Authentication\Infrastructure\Http\Request\ResetPasswordBody;
use App\SharedKernel\Application\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ResetPasswordController
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[OA\Post(summary: 'Set a new password with a reset token', description: 'The token works once. All sessions of the account end.', security: [])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Response(response: 204, description: 'Password changed.')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    #[Route('/api/v1/auth/password/reset', name: 'auth_password_reset', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] ResetPasswordBody $body): Response
    {
        $this->commandBus->dispatch(new ResetPassword($body->token, $body->newPassword));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
