<?php

declare(strict_types=1);

namespace App\Tests\Support\Authorization;

use App\Authorization\Domain\Event\RoleGranted;
use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\Role;
use App\Authorization\Domain\Model\RoleAssignment;
use App\Authorization\Domain\Model\RoleAssignmentId;
use App\Authorization\Domain\Repository\RoleAssignmentRepository;
use App\SharedKernel\Infrastructure\Messaging\CollectedAggregateEvents;
use App\Tests\Support\RepositoryContractTestCase;
use DateTimeImmutable;

use function sprintf;

/**
 * @extends RepositoryContractTestCase<RoleAssignmentRepository>
 */
abstract class RoleAssignmentRepositoryContract extends RepositoryContractTestCase
{
    protected CollectedAggregateEvents $collector;
    protected RoleAssignmentRepository $repository;

    protected function setUp(): void
    {
        $this->collector = new CollectedAggregateEvents();
        $this->repository = $this->createRepository();
    }

    /**
     * Called after every write. The Doctrine tests clear the entity manager here, so that the next read really
     * comes from the database; the in-memory tests have nothing to do.
     */
    protected function forget(): void
    {
    }

    protected function uuid(int $n): string
    {
        return sprintf('01900000-0000-7000-8000-%012d', $n);
    }

    protected function assignment(int $n = 1, int $user = 200, Role $role = Role::Tower): RoleAssignment
    {
        return RoleAssignment::grant(new RoleAssignmentId($this->uuid($n)), new AssignedUserId($this->uuid($user)), $role, new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
    }

    public function testASavedAssignmentIsFoundByUser(): void
    {
        $this->repository->save($this->assignment(1, 200, Role::Admin));
        $this->forget();

        $found = $this->repository->findByUserId(new AssignedUserId($this->uuid(200)));

        self::assertNotNull($found);
        self::assertSame($this->uuid(1), $found->id()->toString());
        self::assertSame($this->uuid(200), $found->userId()->toString());
        self::assertSame(Role::Admin, $found->role());
        self::assertEquals(new DateTimeImmutable('2026-01-01T12:00:00+00:00'), $found->grantedAt());
    }

    public function testAUserWithoutAssignmentGivesNull(): void
    {
        $this->repository->save($this->assignment());
        $this->forget();

        self::assertNull($this->repository->findByUserId(new AssignedUserId($this->uuid(201))));
    }

    public function testSavingRegistersTheEventForPublishing(): void
    {
        $this->repository->save($this->assignment());

        $events = $this->collector->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleGranted::class, $events[0]);
    }
}
