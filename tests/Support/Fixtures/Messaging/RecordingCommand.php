<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Messaging;

use App\SharedKernel\Application\Command;

final readonly class RecordingCommand implements Command
{
    public function __construct(public string $value)
    {
    }
}
