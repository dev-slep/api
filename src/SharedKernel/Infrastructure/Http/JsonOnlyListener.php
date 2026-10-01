<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use function in_array;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rejects POST, PUT and PATCH requests under `/api/*` that do not declare a JSON content type (415).
 */
final readonly class JsonOnlyListener
{
    private const array BODY_METHODS = ['POST', 'PUT', 'PATCH'];

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 24)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !ApiPath::matches($request->getPathInfo()) || !in_array($request->getMethod(), self::BODY_METHODS, true)) {
            return;
        }

        if ('json' !== $request->getContentTypeFormat()) {
            throw new UnsupportedMediaTypeHttpException('Requests must be sent as application/json.');
        }
    }
}
