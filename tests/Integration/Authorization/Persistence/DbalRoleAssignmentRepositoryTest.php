<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authorization\Persistence;

use App\Authorization\Infrastructure\Persistence\DbalRoleAssignmentRepository;
use App\Kernel;
use App\Tests\Support\Authorization\RoleAssignmentRepositoryContract;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(DbalRoleAssignmentRepository::class)]
final class DbalRoleAssignmentRepositoryTest extends RoleAssignmentRepositoryContract
{
    private ?Kernel $kernel = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    protected function createRepository(): object
    {
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();
        $connection = $this->kernel->getContainer()->get('doctrine.dbal.default_connection');

        return new DbalRoleAssignmentRepository($connection, $this->collector);
    }

    public function testThereIsOneAssignmentPerUserInTheDatabase(): void
    {
        $this->repository->save($this->assignment(1, 200));

        $this->expectException(Throwable::class);
        $this->repository->save($this->assignment(2, 200));
    }
}
