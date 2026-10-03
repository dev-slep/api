<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authorization\Application;

use App\Authorization\Application\Command\GrantRole;
use App\Authorization\Application\Command\GrantRoleHandler;
use App\Authorization\Application\Service\AuthorizationEventMapper;
use App\Authorization\Contract\Event\RoleGrantedV1;
use App\Authorization\Domain\Event\RoleGranted;
use App\Authorization\Domain\Exception\UnknownRole;
use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\Role;
use App\Authorization\Infrastructure\Persistence\InMemoryRoleAssignmentRepository;
use App\SharedKernel\Domain\DomainEvent;
use App\Tests\Support\Fake\FrozenClock;
use App\Tests\Support\Fake\SequentialIdGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(GrantRoleHandler::class)]
#[CoversClass(GrantRole::class)]
#[CoversClass(AuthorizationEventMapper::class)]
#[CoversClass(RoleGrantedV1::class)]
final class GrantRoleTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';

    private InMemoryRoleAssignmentRepository $assignments;
    private GrantRoleHandler $handler;

    protected function setUp(): void
    {
        $collector = new \App\SharedKernel\Infrastructure\Messaging\CollectedAggregateEvents();
        $this->assignments = new InMemoryRoleAssignmentRepository($collector);
        $this->handler = new GrantRoleHandler($this->assignments, new FrozenClock(), new SequentialIdGenerator());
    }

    public function testEveryRoleCanBeGranted(): void
    {
        foreach (Role::cases() as $index => $role) {
            $user = sprintf('01900000-0000-7000-8000-%012d', 100 + $index);

            ($this->handler)(new GrantRole($user, $role->value));

            self::assertSame($role, $this->assignments->findByUserId(new AssignedUserId($user))?->role());
        }
    }

    public function testGrantingAgainChangesNothing(): void
    {
        ($this->handler)(new GrantRole(self::USER, 'DRIVER'));
        ($this->handler)(new GrantRole(self::USER, 'ADMIN'));

        $assignment = $this->assignments->findByUserId(new AssignedUserId(self::USER));
        self::assertSame(Role::Driver, $assignment?->role(), 'the first role stays');
    }

    public function testAnUnknownRoleIsRefused(): void
    {
        $this->expectException(UnknownRole::class);

        ($this->handler)(new GrantRole(self::USER, 'SUPERUSER'));
    }

    public function testTheMapperTurnsRoleGrantedIntoItsIntegrationEvent(): void
    {
        $mapper = new AuthorizationEventMapper(new SequentialIdGenerator());
        $at = new DateTimeImmutable('2026-01-02T10:00:00+00:00');
        $domainEvent = new RoleGranted(new AssignedUserId(self::USER), Role::Tower, $at);

        self::assertTrue($mapper->supports($domainEvent));
        $events = $mapper->map($domainEvent);

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(RoleGrantedV1::class, $event);
        self::assertSame('authorization.role_granted.v1', $event->eventName());
        self::assertSame(1, $event->version());
        self::assertSame($at, $event->occurredAt());
        self::assertSame(self::USER, $event->userId->toString());
        self::assertSame('TOWER', $event->role);
        self::assertNotSame('', $event->eventId());
    }

    public function testTheMapperIgnoresOtherEvents(): void
    {
        $mapper = new AuthorizationEventMapper(new SequentialIdGenerator());
        $other = new class implements DomainEvent {
            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
        };

        self::assertFalse($mapper->supports($other));
        self::assertSame([], $mapper->map($other));
    }
}
