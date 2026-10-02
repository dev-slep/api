<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Persistence;

use App\Authentication\Infrastructure\Persistence\InMemoryRefreshTokenRepository;
use App\Tests\Support\Authentication\Repository\RefreshTokenRepositoryContract;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryRefreshTokenRepository::class)]
final class InMemoryRefreshTokenRepositoryTest extends RefreshTokenRepositoryContract
{
    protected function createRepository(): object
    {
        return new InMemoryRefreshTokenRepository();
    }
}
