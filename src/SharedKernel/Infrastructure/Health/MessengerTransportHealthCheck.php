<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Health;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

/**
 * The async transport is reachable: counting its messages queries the transport table.
 */
final readonly class MessengerTransportHealthCheck implements HealthCheck
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.async')]
        private TransportInterface $transport,
    ) {
    }

    public function name(): string
    {
        return 'messenger';
    }

    public function check(): HealthResult
    {
        try {
            if ($this->transport instanceof MessageCountAwareInterface) {
                $this->transport->getMessageCount();
            }
        } catch (Throwable) {
            return HealthResult::unhealthy();
        }

        return HealthResult::healthy();
    }
}
