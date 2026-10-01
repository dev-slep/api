<?php

declare(strict_types=1);

namespace App\Tests\Support\Attribute;

use Attribute;

/**
 * Declares which console command an integration test covers, e.g. `#[CoversConsoleCommand('app:cleanup')]`.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class CoversConsoleCommand
{
    public function __construct(public string $name)
    {
    }
}
