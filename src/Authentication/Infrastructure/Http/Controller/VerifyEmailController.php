<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\VerifyEmail;
use App\Authentication\Infrastructure\Http\Request\TokenBody;
use App\SharedKernel\Application\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class VerifyEmailController
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[OA\Post(summary: 'Confirm an email address', description: 'The token from the verification email works once.', security: [])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Response(response: 204, description: 'Email verified.')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    #[Route('/api/v1/auth/email/verify', name: 'auth_email_verify', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] TokenBody $body): Response
    {
        $this->commandBus->dispatch(new VerifyEmail($body->token));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
