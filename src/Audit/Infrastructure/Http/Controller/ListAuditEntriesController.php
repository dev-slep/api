<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Http\Controller;

use App\Audit\Application\Query\ListAuditEntries;
use App\Audit\Contract\Dto\AuditEntryPage;
use App\Audit\Contract\Dto\AuditEntryView;
use App\Audit\Infrastructure\Http\Request\AuditEntryQuery;
use App\Audit\Infrastructure\Http\Response\AuditEntryResponse;
use App\Audit\Infrastructure\Http\Response\AuditPageResponse;
use App\Authorization\Contract\Permission;
use App\SharedKernel\Application\QueryBus;

use function assert;

use DateTimeInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(Permission::ReadAuditLog->value)]
final readonly class ListAuditEntriesController
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    #[OA\Get(summary: 'Read the audit log', description: 'Newest first. Filters are combined with AND; `from` and `to` are ISO 8601 timestamps.', security: [['bearerAuth' => []]])]
    #[OA\Parameter(name: 'actorId', in: 'query', description: 'Only entries of this user (UUID).', style: 'form', explode: false, allowReserved: false, allowEmptyValue: false, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'targetId', in: 'query', description: 'Only entries about this target (aggregate id).', style: 'form', explode: false, allowReserved: false, allowEmptyValue: false, schema: new OA\Schema(type: 'string', maxLength: 64))]
    #[OA\Parameter(name: 'name', in: 'query', description: 'Command name (`RegisterUser`) or event name (`authentication.user_logged_in.v1`).', style: 'form', explode: false, allowReserved: false, allowEmptyValue: false, schema: new OA\Schema(type: 'string', maxLength: 160))]
    #[OA\Parameter(name: 'kind', in: 'query', style: 'form', explode: false, allowReserved: false, allowEmptyValue: false, schema: new OA\Schema(type: 'string', enum: ['command', 'event']))]
    #[OA\Parameter(name: 'from', in: 'query', description: 'Not before this moment (ISO 8601).', style: 'form', explode: false, allowReserved: false, allowEmptyValue: false, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'to', in: 'query', description: 'Not after this moment (ISO 8601).', style: 'form', explode: false, allowReserved: false, allowEmptyValue: false, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'page', in: 'query', description: 'Starts at 1.', style: 'form', explode: false, allowReserved: false, allowEmptyValue: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', style: 'form', explode: false, allowReserved: false, allowEmptyValue: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50))]
    #[OA\Tag(name: 'Audit')]
    #[OA\Response(response: 200, description: 'One page of audit entries.', content: new OA\JsonContent(ref: new Model(type: AuditPageResponse::class)))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    #[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[Route('/api/v1/admin/audit', name: 'admin_audit_list', methods: ['GET'])]
    public function __invoke(#[MapQueryString] AuditEntryQuery $query = new AuditEntryQuery()): JsonResponse
    {
        $page = $this->queryBus->ask(new ListAuditEntries($query->actorId, $query->targetId, $query->name, $query->kind, $query->from, $query->to, $query->page, $query->perPage));
        assert($page instanceof AuditEntryPage);

        return new JsonResponse(new AuditPageResponse(
            array_map($this->response(...), $page->items),
            $page->page,
            $page->perPage,
            $page->total,
        ));
    }

    private function response(AuditEntryView $view): AuditEntryResponse
    {
        return new AuditEntryResponse(
            $view->id,
            $view->occurredAt->format(DateTimeInterface::ATOM),
            $view->kind,
            $view->name,
            $view->actorType,
            $view->actorId,
            $view->actorRole,
            $view->targetId,
            [] === $view->payload ? new stdClass() : $view->payload,
            $view->outcome,
            $view->failureReason,
            $view->correlationId,
            $view->ip,
            $view->userAgent,
        );
    }
}
