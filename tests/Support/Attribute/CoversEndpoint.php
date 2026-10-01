<?php

declare(strict_types=1);

namespace App\Tests\Support\Attribute;

use Attribute;

/**
 * Declares which HTTP endpoint an integration test covers, e.g. `#[CoversEndpoint('GET', '/health/live')]`.
 * The path is written exactly as in the controller's `#[Route]` attributes.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class CoversEndpoint
{
    public string $method;

    public function __construct(string $method, public string $path)
    {
        $this->method = strtoupper($method);
    }

    public function key(): string
    {
        return $this->method.' '.$this->path;
    }
}
