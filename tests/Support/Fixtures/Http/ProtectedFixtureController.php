<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Test-only routes under the protected `/api/v1` prefix, so the access rules can be tested before the business
 * endpoints exist. The OpenAPI area excludes these paths.
 */
#[AsController]
final readonly class ProtectedFixtureController
{
    #[Route('/api/v1/_test/authenticated', methods: ['GET'])]
    public function authenticated(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/api/v1/admin/_test/admin', methods: ['GET'])]
    public function admin(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
