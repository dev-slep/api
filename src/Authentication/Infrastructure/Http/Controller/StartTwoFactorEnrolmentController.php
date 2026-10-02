<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Http\Controller;

use App\Authentication\Application\Command\Result\TwoFactorEnrolment;
use App\Authentication\Application\Command\StartTwoFactorEnrolment;
use App\Authentication\Contract\CurrentUser;
use App\Authentication\Infrastructure\Http\Response\EnrolmentResponse;
use App\SharedKernel\Application\CommandBus;

use function assert;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class StartTwoFactorEnrolmentController
{
    public function __construct(
        private CommandBus $commandBus,
        private CurrentUser $currentUser,
    ) {
    }

    #[OA\Post(summary: 'Start two-factor enrolment', description: 'Returns a new secret and the otpauth URI. Refused once the enrolment is confirmed.', security: [['bearerAuth' => []]])]
    #[OA\Tag(name: 'Admin authentication')]
    #[OA\Response(response: 200, description: 'The secret, shown once.', content: new OA\JsonContent(ref: new Model(type: EnrolmentResponse::class)))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    #[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
    #[OA\Response(response: 409, ref: '#/components/responses/Conflict')]
    #[Route('/api/v1/admin/auth/2fa/enrol', name: 'admin_auth_2fa_enrol', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        $enrolment = $this->commandBus->dispatch(new StartTwoFactorEnrolment($this->currentUser->id()->toString()));
        assert($enrolment instanceof TwoFactorEnrolment);

        return new JsonResponse(new EnrolmentResponse($enrolment->secret, $enrolment->provisioningUri));
    }
}
