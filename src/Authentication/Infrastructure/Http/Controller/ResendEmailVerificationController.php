<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\ResendEmailVerification;
use App\Authentication\Infrastructure\Http\Request\EmailBody;
use App\SharedKernel\Application\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ResendEmailVerificationController
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[OA\Post(summary: 'Send the verification email again', description: 'Always answers 202, whether or not the email belongs to an unverified account. Earlier links stop working.', security: [])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Response(response: 202, description: 'If an unverified account exists, a new link was sent.')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    #[Route('/api/v1/auth/email/resend', name: 'auth_email_resend', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] EmailBody $body): Response
    {
        $this->commandBus->dispatch(new ResendEmailVerification($body->email));

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
