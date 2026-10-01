<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Correlation;

use Symfony\Contracts\Service\ResetInterface;

/**
 * The correlation and causation ids of whatever is running right now (an HTTP request or a worker message).
 */
final class CorrelationContext implements ResetInterface
{
    private ?string $correlationId = null;
    private ?string $causationId = null;

    public function start(string $correlationId, ?string $causationId = null): void
    {
        $this->correlationId = $correlationId;
        $this->causationId = $causationId;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    public function causationId(): ?string
    {
        return $this->causationId;
    }

    public function reset(): void
    {
        $this->correlationId = null;
        $this->causationId = null;
    }
}
