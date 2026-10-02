<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use App\SharedKernel\Domain\DomainException;
use App\SharedKernel\Domain\ProblemType;
use Throwable;

/**
 * The social provider's signing keys could not be fetched: the sign-in can neither be accepted nor rejected.
 */
final class SocialProviderUnavailable extends DomainException implements ProblemType
{
    public function __construct(string $message = 'The sign-in provider is not reachable.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function problemSlug(): string
    {
        return 'social-provider-unavailable';
    }

    public function httpStatus(): int
    {
        return 503;
    }
}
