<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Correlation;

use App\SharedKernel\Domain\IdGenerator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Takes `X-Correlation-Id` from the request (or generates one) and returns it in the response.
 */
final readonly class CorrelationHttpListener
{
    public const string HEADER = 'X-Correlation-Id';

    public function __construct(
        private CorrelationContext $context,
        private IdGenerator $ids,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $supplied = $event->getRequest()->headers->get(self::HEADER);
        $correlationId = null !== $supplied && 1 === preg_match('/^[A-Za-z0-9._-]{1,100}$/D', $supplied)
            ? $supplied
            : $this->ids->generate();

        $this->context->start($correlationId);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $correlationId = $this->context->correlationId();
        if (null !== $correlationId) {
            $event->getResponse()->headers->set(self::HEADER, $correlationId);
        }
    }
}
