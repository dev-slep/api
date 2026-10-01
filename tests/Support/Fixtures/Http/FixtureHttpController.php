<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Http;

use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use App\Tests\Support\Fixtures\Messaging\FixtureDomainException;
use App\Tests\Support\Fixtures\Outbox\CreateFixtureCommand;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Test-only controller (registered under when@test) that raises every kind of error.
 */
#[AsController]
final readonly class FixtureHttpController
{
    public function __construct(
        private SerializerInterface $serializer,
        private CommandBus $commandBus,
    ) {
    }

    #[Route('/api/_test/problem', methods: ['GET'])]
    public function problem(): never
    {
        throw new FixtureProblemException('Fixture conflict happened.');
    }

    #[Route('/api/_test/domain-error', methods: ['GET'])]
    public function domainError(): never
    {
        throw new FixtureDomainException('Plain domain rule violated.');
    }

    #[Route('/api/_test/payload', methods: ['POST'])]
    public function payload(#[MapRequestPayload] FixturePayload $payload): JsonResponse
    {
        return new JsonResponse(['name' => $payload->name, 'count' => $payload->count], Response::HTTP_CREATED);
    }

    #[Route('/api/_test/not-found', methods: ['GET'])]
    public function notFound(): never
    {
        throw new NotFoundHttpException('Nothing here.');
    }

    #[Route('/api/_test/forbidden', methods: ['GET'])]
    public function forbidden(): never
    {
        throw new AccessDeniedHttpException();
    }

    #[Route('/api/_test/unauthorized', methods: ['GET'])]
    public function unauthorized(): never
    {
        throw new UnauthorizedHttpException('Bearer');
    }

    #[Route('/api/_test/boom', methods: ['GET'])]
    public function boom(): never
    {
        throw new RuntimeException('secret internal detail');
    }

    #[Route('/api/_test/no-body', methods: ['POST', 'PUT', 'PATCH'])]
    public function noBody(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/_test/serialized', methods: ['GET'])]
    public function serialized(): Response
    {
        $json = $this->serializer->serialize([
            'price' => new Money(150000, new Currency('RSD')),
            'createdAt' => new DateTimeImmutable('2026-03-01 12:30:00', new DateTimeZone('Europe/Belgrade')),
            'someValue' => 1,
        ], 'json');

        return new Response($json, headers: ['Content-Type' => 'application/json']);
    }

    #[Route('/api/_test/dispatch', methods: ['POST'])]
    public function dispatch(): Response
    {
        $this->commandBus->dispatch(new CreateFixtureCommand('01900000-0000-7000-8000-0000000000e1'));

        return new Response(status: Response::HTTP_ACCEPTED);
    }

    #[Route('/outside-api/_test/boom', methods: ['GET'])]
    public function outsideApi(): never
    {
        throw new RuntimeException('outside');
    }
}
