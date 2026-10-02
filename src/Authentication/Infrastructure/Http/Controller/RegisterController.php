<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\RegisterUser;
use App\Authentication\Infrastructure\Http\Request\RegisterBody;
use App\Authentication\Infrastructure\Http\Response\RegisterResponse;
use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Infrastructure\Http\IdempotentRequests;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class RegisterController
{
    public function __construct(
        private CommandBus $commandBus,
        private IdempotentRequests $idempotentRequests,
    ) {
    }

    #[OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, description: 'Makes the request safe to retry: the same key with the same body replays the first response.', style: 'simple', explode: false, schema: new OA\Schema(type: 'string', maxLength: 255))]
    #[OA\Post(summary: 'Register a driver or tower account', description: 'Creates an unverified account and emails a verification link. No tokens are issued: the email has to be verified before the first login.', security: [])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Response(response: 201, description: 'The account was created.', content: new OA\JsonContent(ref: new Model(type: RegisterResponse::class)))]
    #[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
    #[OA\Response(response: 409, ref: '#/components/responses/Conflict')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    #[Route('/api/v1/auth/register', name: 'auth_register', methods: ['POST'])]
    public function __invoke(Request $request, #[MapRequestPayload] RegisterBody $body): Response
    {
        return $this->idempotentRequests->run($request, function () use ($request, $body): JsonResponse {
            $this->commandBus->dispatch(new RegisterUser($body->email, $body->password, $body->role, $body->phone, $body->locale ?? $request->getLocale()));

            return new JsonResponse(new RegisterResponse(mb_strtolower(trim($body->email)), true), Response::HTTP_CREATED);
        });
    }
}
