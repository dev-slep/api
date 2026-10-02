<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Persistence;

use App\Authentication\Infrastructure\Persistence\InMemoryOneTimeTokenRepository;
use App\Tests\Support\Authentication\Repository\OneTimeTokenRepositoryContract;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryOneTimeTokenRepository::class)]
final class InMemoryOneTimeTokenRepositoryTest extends OneTimeTokenRepositoryContract
{
    protected function createRepository(): object
    {
        return new InMemoryOneTimeTokenRepository();
    }
}
