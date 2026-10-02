<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Persistence;

use App\Authentication\Infrastructure\Persistence\InMemoryTwoFactorSecretRepository;
use App\Tests\Support\Authentication\Repository\TwoFactorSecretRepositoryContract;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryTwoFactorSecretRepository::class)]
final class InMemoryTwoFactorSecretRepositoryTest extends TwoFactorSecretRepositoryContract
{
    protected function createRepository(): object
    {
        return new InMemoryTwoFactorSecretRepository($this->collector);
    }
}
