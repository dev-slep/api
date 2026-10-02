<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\RequestPasswordReset;
use App\Authentication\Infrastructure\Http\Request\EmailBody;
use App\Authentication\Infrastructure\Http\RequestContextFactory;
use App\SharedKernel\Application\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ForgotPasswordController
{
    public function __construct(
        private CommandBus $commandBus,
        private RequestContextFactory $contexts,
    ) {
    }

    #[OA\Post(summary: 'Request a password reset email', description: 'Always answers 202, whether or not the email belongs to an account.', security: [])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Response(response: 202, description: 'If the account exists, a reset link was sent.')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    #[Route('/api/v1/auth/password/forgot', name: 'auth_password_forgot', methods: ['POST'])]
    public function __invoke(Request $request, #[MapRequestPayload] EmailBody $body): Response
    {
        $this->commandBus->dispatch(new RequestPasswordReset($body->email, $this->contexts->fromRequest($request)));

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
