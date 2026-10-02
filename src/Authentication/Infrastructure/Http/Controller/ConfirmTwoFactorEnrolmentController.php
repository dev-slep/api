<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\ConfirmTwoFactorEnrolment;
use App\Authentication\Application\Command\Result\RecoveryCodes;
use App\Authentication\Contract\CurrentUser;
use App\Authentication\Infrastructure\Http\Request\CodeBody;
use App\Authentication\Infrastructure\Http\Response\RecoveryCodesResponse;
use App\SharedKernel\Application\CommandBus;

use function assert;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ConfirmTwoFactorEnrolmentController
{
    public function __construct(
        private CommandBus $commandBus,
        private CurrentUser $currentUser,
    ) {
    }

    #[OA\Post(summary: 'Confirm two-factor enrolment with a first code', description: 'Returns the single-use recovery codes, shown once.', security: [['bearerAuth' => []]])]
    #[OA\Tag(name: 'Admin authentication')]
    #[OA\Response(response: 200, description: 'The recovery codes.', content: new OA\JsonContent(ref: new Model(type: RecoveryCodesResponse::class)))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    #[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
    #[OA\Response(response: 409, ref: '#/components/responses/Conflict')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[Route('/api/v1/admin/auth/2fa/enrol/confirm', name: 'admin_auth_2fa_enrol_confirm', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CodeBody $body): JsonResponse
    {
        $codes = $this->commandBus->dispatch(new ConfirmTwoFactorEnrolment($this->currentUser->id()->toString(), $body->code));
        assert($codes instanceof RecoveryCodes);

        return new JsonResponse(new RecoveryCodesResponse($codes->codes));
    }
}
