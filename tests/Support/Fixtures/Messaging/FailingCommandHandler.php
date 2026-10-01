<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Messaging;

use App\SharedKernel\Application\CommandHandler;

final readonly class FailingCommandHandler implements CommandHandler
{
    public function __invoke(FailingCommand $command): void
    {
        throw new FixtureDomainException('Handler failed on purpose.');
    }
}
