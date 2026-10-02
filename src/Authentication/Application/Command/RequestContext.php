<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

/**
 * Where a request came from, recorded in the security events.
 */
final readonly class RequestContext
{
    public function __construct(
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {
    }
}
