<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Outbox;

use App\SharedKernel\Application\Command;

final readonly class CreateFixtureCommand implements Command
{
    public function __construct(
        public string $id,
        public bool $failAfterRecording = false,
        public bool $insertRow = false,
    ) {
    }
}
