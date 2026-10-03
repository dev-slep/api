<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit\Infrastructure;

use App\Audit\Application\Service\AuditRecorder;
use App\Audit\Application\Service\PayloadExtractor;
use App\Audit\Domain\Model\Actor;
use App\Audit\Domain\Model\ActorType;
use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Model\AuditEntryFilter;
use App\Audit\Domain\Model\AuditEntryId;
use App\Audit\Domain\Model\AuditKind;
use App\Audit\Domain\Model\Outcome;
use App\Audit\Domain\Policy\PayloadMasker;
use App\Audit\Domain\Repository\AuditEntryRepository;
use App\Audit\Infrastructure\Messaging\AuditCommandAuditor;
use App\Audit\Infrastructure\Messaging\RecordIntegrationEventSubscriber;
use App\Audit\Infrastructure\Persistence\AuditProcessedEvents;
use App\Audit\Infrastructure\Persistence\DbalAuditEntryRepository;
use App\Audit\Infrastructure\Persistence\InMemoryAuditEntryRepository;
use App\Authentication\Contract\CurrentUser;
use App\SharedKernel\Application\Command;
use App\SharedKernel\Application\IdempotentHandling;
use App\SharedKernel\Application\ProcessedEvents;
use App\SharedKernel\Contract\IntegrationEvent;
use App\SharedKernel\Contract\UserId;
use App\SharedKernel\Infrastructure\Correlation\CorrelationContext;
use App\Tests\Support\Fake\FrozenClock;
use App\Tests\Support\Fake\NullTransaction;
use App\Tests\Support\Fake\SequentialIdGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

use function sprintf;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(InMemoryAuditEntryRepository::class)]
#[CoversClass(DbalAuditEntryRepository::class)]
#[CoversClass(AuditProcessedEvents::class)]
#[CoversClass(AuditCommandAuditor::class)]
#[CoversClass(RecordIntegrationEventSubscriber::class)]
final class AuditInfrastructureTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';

    private function entry(int $n, string $when = '2026-01-01T12:00:00+00:00', ?string $actor = self::USER, string $name = 'Login'): AuditEntry
    {
        return new AuditEntry(
            new AuditEntryId(sprintf('01900000-0000-7000-8000-%012d', $n)),
            new DateTimeImmutable($when),
            AuditKind::Command,
            $name,
            null === $actor ? Actor::system() : Actor::user($actor, 'ROLE_ADMIN'),
            null,
            ['a' => 1],
            Outcome::Success,
            null,
            null,
            null,
            null,
        );
    }

    public function testTheInMemoryLogSearchesNewestFirstAndPages(): void
    {
        $log = new InMemoryAuditEntryRepository();
        $log->add($this->entry(1, '2026-01-01T10:00:00+00:00'));
        $log->add($this->entry(2, '2026-01-01T12:00:00+00:00', null));
        $log->add($this->entry(3, '2026-01-01T11:00:00+00:00', name: 'Logout'));

        self::assertSame(
            ['01900000-0000-7000-8000-000000000002', '01900000-0000-7000-8000-000000000003'],
            array_map(static fn (AuditEntry $e): string => $e->id->toString(), $log->search(new AuditEntryFilter(), 1, 2)),
        );
        self::assertCount(1, $log->search(new AuditEntryFilter(), 2, 2));
        self::assertSame(2, $log->count(new AuditEntryFilter(actorId: self::USER)));
        self::assertCount(3, $log->all());
    }

    public function testTheSqlRepositoryInsertsOneRowWithTheMaskedPayloadAsJson(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->with(
            self::stringContains('INSERT INTO audit.audit_entry'),
            self::callback(static fn (array $p): bool => '{"a":1}' === $p['payload'] && 'command' === $p['kind'] && self::USER === $p['actorId'] && '2026-01-01 12:00:00.000000+00:00' === $p['occurredAt']),
        );

        (new DbalAuditEntryRepository($connection))->add($this->entry(1));
    }

    public function testTheSqlRepositoryFiltersPagesAndHydratesRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->with(
            self::logicalAnd(self::stringContains('WHERE actor_id = :actorId AND kind = :kind'), self::stringContains('ORDER BY occurred_at DESC')),
            ['actorId' => self::USER, 'kind' => 'command', 'limit' => 10, 'offset' => 10],
        )->willReturn([
            ['id' => '01900000-0000-7000-8000-000000000001', 'occurred_at' => '2026-01-01 12:00:00.000000+00', 'kind' => 'command', 'name' => 'Login', 'actor_type' => 'user', 'actor_id' => self::USER, 'actor_role' => 'ROLE_ADMIN', 'target_id' => null, 'payload' => '{"a":1}', 'outcome' => 'success', 'failure_reason' => null, 'correlation_id' => 'c', 'ip' => null, 'user_agent' => null],
            ['id' => '01900000-0000-7000-8000-000000000002', 'occurred_at' => '2026-01-01 12:00:00.000000+00', 'kind' => 'event', 'name' => 'x.v1', 'actor_type' => 'anonymous', 'actor_id' => null, 'actor_role' => null, 'target_id' => 't', 'payload' => '[]', 'outcome' => 'recorded', 'failure_reason' => null, 'correlation_id' => null, 'ip' => '1.1.1.1', 'user_agent' => 'ua'],
            ['id' => '01900000-0000-7000-8000-000000000003', 'occurred_at' => '2026-01-01 12:00:00.000000+00', 'kind' => 'command', 'name' => 'Cron', 'actor_type' => 'system', 'actor_id' => null, 'actor_role' => null, 'target_id' => null, 'payload' => '{}', 'outcome' => 'failure', 'failure_reason' => 'boom', 'correlation_id' => null, 'ip' => null, 'user_agent' => null],
        ]);

        $entries = (new DbalAuditEntryRepository($connection))->search(new AuditEntryFilter(actorId: self::USER, kind: AuditKind::Command), 2, 10);

        self::assertCount(3, $entries);
        self::assertSame(self::USER, $entries[0]->actor->userId);
        self::assertSame(['a' => 1], $entries[0]->payload);
        self::assertSame(ActorType::Anonymous, $entries[1]->actor->type);
        self::assertSame('t', $entries[1]->targetId);
        self::assertSame(ActorType::System, $entries[2]->actor->type);
        self::assertSame('boom', $entries[2]->failureReason);
    }

    public function testTheSqlRepositoryCountsWithEveryCriterion(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with(
            self::stringContains('WHERE actor_id = :actorId AND target_id = :targetId AND name = :name AND kind = :kind AND occurred_at >= :from AND occurred_at <= :to'),
            self::anything(),
        )->willReturn('7');

        $moment = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

        self::assertSame(7, (new DbalAuditEntryRepository($connection))->count(new AuditEntryFilter(self::USER, 't', 'n', AuditKind::Event, $moment, $moment)));
    }

    public function testTheProcessedEventsTableIsTheModulesOwn(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with(self::stringContains('audit.processed_event'))->willReturn(false);

        self::assertFalse((new AuditProcessedEvents($connection))->wasProcessed('sub', 'event'));
    }

    private function auditor(AuditEntryRepository $log, ?CurrentUser $user, ?Request $request, ?string $correlation = 'corr'): AuditCommandAuditor
    {
        $requests = new RequestStack();
        if (null !== $request) {
            $requests->push($request);
        }
        $context = new CorrelationContext();
        if (null !== $correlation) {
            $context->start($correlation);
        }

        return new AuditCommandAuditor(
            new AuditRecorder($log, new PayloadExtractor(), new PayloadMasker(), new FrozenClock(), new SequentialIdGenerator()),
            $user ?? $this->currentUser(false),
            $requests,
            $context,
            new NullLogger(),
        );
    }

    private function currentUser(bool $authenticated): CurrentUser
    {
        $user = self::createStub(CurrentUser::class);
        $user->method('isAuthenticated')->willReturn($authenticated);
        $user->method('id')->willReturn(new UserId(self::USER));
        $user->method('roles')->willReturn(['ROLE_ADMIN']);

        return $user;
    }

    private function command(): Command
    {
        return new class implements Command {
            public string $password = 'secret';
        };
    }

    public function testASignedInUsersCommandIsAttributedToThemWithTheirRequestDetails(): void
    {
        $log = new InMemoryAuditEntryRepository();
        $request = Request::create('/x', 'POST', server: ['REMOTE_ADDR' => '10.1.1.1', 'HTTP_USER_AGENT' => str_repeat('a', 400)]);

        $this->auditor($log, $this->currentUser(true), $request)->recordSuccess($this->command());

        $entry = $log->all()[0];
        self::assertSame(self::USER, $entry->actor->userId);
        self::assertSame('ROLE_ADMIN', $entry->actor->role);
        self::assertSame('10.1.1.1', $entry->ip);
        self::assertSame(255, mb_strlen((string) $entry->userAgent));
        self::assertSame('corr', $entry->correlationId);
        self::assertSame('***', $entry->payload['password']);
    }

    public function testWithoutAUserTheActorIsAnonymousOnTheWebAndTheSystemElsewhere(): void
    {
        $log = new InMemoryAuditEntryRepository();

        $this->auditor($log, null, Request::create('/x'))->recordSuccess($this->command());
        $this->auditor($log, null, null, null)->recordSuccess($this->command());

        self::assertSame(ActorType::Anonymous, $log->all()[0]->actor->type);
        self::assertSame(ActorType::System, $log->all()[1]->actor->type);
        self::assertNull($log->all()[1]->correlationId);
    }

    public function testAFailureIsRecordedWithItsReason(): void
    {
        $log = new InMemoryAuditEntryRepository();

        $this->auditor($log, null, null)->recordFailure($this->command(), new RuntimeException('nope'));

        self::assertSame(Outcome::Failure, $log->all()[0]->outcome);
        self::assertSame('RuntimeException: nope', $log->all()[0]->failureReason);
    }

    public function testAnAuditFailureNeverBreaksTheCommand(): void
    {
        $broken = new class implements AuditEntryRepository {
            public function add(AuditEntry $entry): void
            {
                throw new LogicException('database is down');
            }

            public function search(AuditEntryFilter $filter, int $page, int $perPage): array
            {
                return [];
            }

            public function count(AuditEntryFilter $filter): int
            {
                return 0;
            }
        };

        $this->auditor($broken, null, null)->recordSuccess($this->command());

        $this->addToAssertionCount(1);
    }

    public function testTheSubscriberStoresAnEventOnceEvenWhenItIsRedelivered(): void
    {
        $log = new InMemoryAuditEntryRepository();
        $processed = new class implements ProcessedEvents {
            /** @var array<string, true> */
            private array $seen = [];

            public function wasProcessed(string $subscriber, string $eventId): bool
            {
                return isset($this->seen[$subscriber.$eventId]);
            }

            public function markProcessed(string $subscriber, string $eventId): void
            {
                $this->seen[$subscriber.$eventId] = true;
            }
        };
        $context = new CorrelationContext();
        $context->start('corr-9');
        $subscriber = new RecordIntegrationEventSubscriber(
            new AuditRecorder($log, new PayloadExtractor(), new PayloadMasker(), new FrozenClock(), new SequentialIdGenerator()),
            new IdempotentHandling($processed, new NullTransaction()),
            $context,
        );
        $event = new readonly class implements IntegrationEvent {
            public string $accountId;

            public function __construct()
            {
                $this->accountId = '01900000-0000-7000-8000-0000000000aa';
            }

            public function eventId(): string
            {
                return 'e-1';
            }

            public function eventName(): string
            {
                return 'authentication.user_logged_in.v1';
            }

            public function version(): int
            {
                return 1;
            }

            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-01-01T12:00:00+00:00');
            }
        };

        $subscriber($event);
        $subscriber($event);

        self::assertCount(1, $log->all());
        self::assertSame('corr-9', $log->all()[0]->correlationId);
    }
}
