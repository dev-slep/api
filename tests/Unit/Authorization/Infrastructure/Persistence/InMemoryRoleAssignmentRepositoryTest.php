<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authorization\Infrastructure\Persistence;

use App\Authorization\Infrastructure\Persistence\InMemoryRoleAssignmentRepository;
use App\Tests\Support\Authorization\RoleAssignmentRepositoryContract;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryRoleAssignmentRepository::class)]
final class InMemoryRoleAssignmentRepositoryTest extends RoleAssignmentRepositoryContract
{
    protected function createRepository(): object
    {
        return new InMemoryRoleAssignmentRepository($this->collector);
    }
}
