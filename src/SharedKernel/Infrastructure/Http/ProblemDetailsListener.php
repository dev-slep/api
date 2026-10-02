<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Responds with RFC 9457 problem details to every exception raised under `/api/*`.
 */
final readonly class ProblemDetailsListener
{
    public function __construct(private ProblemDetailsFactory $factory)
    {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 16)]
    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!ApiPath::matches($request->getPathInfo())) {
            return;
        }

        // Security exceptions belong to the firewall: it starts authentication (401) or denies access (403)
        // through its entry point and access denied handler, which answer with the same problem details.
        $failure = $event->getThrowable();
        if ($failure instanceof AccessDeniedException || $failure instanceof AuthenticationException) {
            return;
        }

        $event->setResponse($this->factory->create($failure, $request->getPathInfo(), $request->getLocale()));
    }
}
