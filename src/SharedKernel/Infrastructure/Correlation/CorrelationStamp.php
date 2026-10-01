<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Correlation;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Travels with a message (also through the transport) so the worker can restore the tracing context.
 */
final readonly class CorrelationStamp implements StampInterface
{
    public function __construct(
        public string $correlationId,
        public ?string $causationId = null,
    ) {
    }
}
