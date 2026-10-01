<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Logging;

use App\SharedKernel\Infrastructure\Correlation\CorrelationContext;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/**
 * Adds `correlationId` and `causationId` to every log record, from HTTP requests and the worker alike.
 */
#[AsMonologProcessor]
final readonly class CorrelationProcessor
{
    public function __construct(private CorrelationContext $context)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $correlationId = $this->context->correlationId();
        if (null === $correlationId) {
            return $record;
        }

        $extra = $record->extra;
        $extra['correlationId'] = $correlationId;
        $causationId = $this->context->causationId();
        if (null !== $causationId) {
            $extra['causationId'] = $causationId;
        }

        return $record->with(extra: $extra);
    }
}
