<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\Result\AuthenticationResult;
use App\Authentication\Application\Command\VerifyTwoFactor;
use App\Authentication\Contract\CurrentUser;
use App\Authentication\Infrastructure\Http\Request\CodeBody;
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
final readonly class VerifyTwoFactorController
{
    public function __construct(
        private CommandBus $commandBus,
        private CurrentUser $currentUser,
        private RequestContextFactory $contexts,
    ) {
    }

    #[OA\Post(summary: 'Complete the admin login with a TOTP or recovery code', description: 'Needs the two-factor-pending token from the password login.', security: [['bearerAuth' => []]])]
    #[OA\Tag(name: 'Admin authentication')]
    #[OA\Response(response: 200, description: 'Admin tokens.', content: new OA\JsonContent(ref: new Model(type: TokenResponse::class)))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    #[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
    #[OA\Response(response: 409, ref: '#/components/responses/Conflict')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    #[Route('/api/v1/admin/auth/2fa/verify', name: 'admin_auth_2fa_verify', methods: ['POST'])]
    public function __invoke(Request $request, #[MapRequestPayload] CodeBody $body): JsonResponse
    {
        $result = $this->commandBus->dispatch(new VerifyTwoFactor($this->currentUser->id()->toString(), $body->code, $this->contexts->fromRequest($request)));
        assert($result instanceof AuthenticationResult);

        return AuthenticationResponses::from($result);
    }
}
