<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Persistence;

use App\Authentication\Infrastructure\Persistence\InMemoryUserAccountRepository;
use App\Tests\Support\Authentication\Repository\UserAccountRepositoryContract;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryUserAccountRepository::class)]
final class InMemoryUserAccountRepositoryTest extends UserAccountRepositoryContract
{
    protected function createRepository(): object
    {
        return new InMemoryUserAccountRepository($this->collector);
    }
}
