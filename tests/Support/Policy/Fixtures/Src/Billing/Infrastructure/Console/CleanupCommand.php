<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Src\Billing\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'billing:cleanup')]
final class CleanupCommand
{
    public function run(): void
    {
    }
}
