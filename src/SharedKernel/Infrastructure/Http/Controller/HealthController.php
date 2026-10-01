<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http\Controller;

use App\SharedKernel\Infrastructure\Health\HealthCheck;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Probes for container orchestration: liveness (the process answers) and readiness (its dependencies work).
 * Outside `/api`: no authentication, no JSON-only rule.
 */
#[AsController]
final readonly class HealthController
{
    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(
        #[AutowireIterator('app.health_check')]
        private iterable $checks,
    ) {
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        $results = [];
        $allHealthy = true;
        foreach ($this->checks as $check) {
            $healthy = $check->check()->healthy;
            $allHealthy = $allHealthy && $healthy;
            $results[$check->name()] = ['status' => $healthy ? 'ok' : 'fail'];
        }

        return new JsonResponse(
            ['status' => $allHealthy ? 'ok' : 'fail', 'checks' => (object) $results],
            $allHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
