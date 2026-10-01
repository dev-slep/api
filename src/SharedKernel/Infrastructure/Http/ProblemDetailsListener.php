<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

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

        $event->setResponse($this->factory->create($event->getThrowable(), $request->getPathInfo(), $request->getLocale()));
    }
}
