<?php

declare(strict_types=1);

namespace App\Tests\Integration\Audit;

use App\Audit\Domain\Model\Actor;
use App\Audit\Domain\Model\ActorType;
use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Model\AuditEntryFilter;
use App\Audit\Domain\Model\AuditEntryId;
use App\Audit\Domain\Model\AuditKind;
use App\Audit\Domain\Model\Outcome;
use App\Audit\Infrastructure\Persistence\DbalAuditEntryRepository;
use App\Kernel;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * The real table: entries round-trip and can be searched, and the database itself refuses to change or remove one.
 */
#[CoversClass(DbalAuditEntryRepository::class)]
final class AuditStorageTest extends TestCase
{
    private ?Kernel $kernel = null;
    private Connection $connection;
    private DbalAuditEntryRepository $repository;

    protected function setUp(): void
    {
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();
        $connection = $this->kernel->getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
        $this->repository = new DbalAuditEntryRepository($connection);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
    }

    private function entry(int $n, string $when, Actor $actor, string $name = 'Login', ?string $target = 't-1'): AuditEntry
    {
        return new AuditEntry(
            new AuditEntryId(sprintf('01900000-0000-7000-8000-%012d', $n)),
            new DateTimeImmutable($when),
            AuditKind::Command,
            $name,
            $actor,
            $target,
            ['email' => 'ana@example.com', 'nested' => ['ščž' => 'ćđ'], 'n' => 3],
            Outcome::Failure,
            'boom',
            'corr-1',
            '203.0.113.9',
            'Agent/1.0',
        );
    }

    public function testAnEntryRoundTrips(): void
    {
        $actor = Actor::user('01900000-0000-7000-8000-0000000000aa', 'ROLE_ADMIN');
        $this->repository->add($this->entry(1, '2026-03-04T05:06:07.123456+02:00', $actor));

        $found = $this->repository->search(new AuditEntryFilter(), 1, 10)[0];

        self::assertSame('01900000-0000-7000-8000-000000000001', $found->id->toString());
        self::assertSame('2026-03-04T03:06:07.123456+00:00', $found->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(AuditKind::Command, $found->kind);
        self::assertSame('Login', $found->name);
        self::assertSame(ActorType::User, $found->actor->type);
        self::assertSame('ROLE_ADMIN', $found->actor->role);
        self::assertSame('t-1', $found->targetId);
        self::assertEqualsCanonicalizing(['email' => 'ana@example.com', 'nested' => ['ščž' => 'ćđ'], 'n' => 3], $found->payload, 'jsonb does not keep the key order');
        self::assertSame(Outcome::Failure, $found->outcome);
        self::assertSame('boom', $found->failureReason);
        self::assertSame('corr-1', $found->correlationId);
        self::assertSame('203.0.113.9', $found->ip);
        self::assertSame('Agent/1.0', $found->userAgent);
    }

    public function testSearchCombinesFiltersOrdersNewestFirstAndPages(): void
    {
        $ana = Actor::user('01900000-0000-7000-8000-0000000000aa', null);
        $this->repository->add($this->entry(1, '2026-01-01T10:00:00+00:00', $ana));
        $this->repository->add($this->entry(2, '2026-01-01T12:00:00+00:00', Actor::system(), 'Purge', null));
        $this->repository->add($this->entry(3, '2026-01-01T11:00:00+00:00', Actor::anonymous(), 'Login', 't-2'));

        $names = fn (AuditEntryFilter $f, int $page = 1, int $per = 10): array => array_map(static fn (AuditEntry $e): string => $e->id->toString(), $this->repository->search($f, $page, $per));

        self::assertSame(['01900000-0000-7000-8000-000000000002', '01900000-0000-7000-8000-000000000003', '01900000-0000-7000-8000-000000000001'], $names(new AuditEntryFilter()));
        self::assertSame(['01900000-0000-7000-8000-000000000003'], $names(new AuditEntryFilter(), 2, 1));
        self::assertSame(['01900000-0000-7000-8000-000000000001'], $names(new AuditEntryFilter(actorId: $ana->userId)));
        self::assertSame(['01900000-0000-7000-8000-000000000003'], $names(new AuditEntryFilter(targetId: 't-2')));
        self::assertSame(['01900000-0000-7000-8000-000000000002'], $names(new AuditEntryFilter(name: 'Purge')));
        self::assertSame(2, $this->repository->count(new AuditEntryFilter(name: 'Login')));
        self::assertSame(2, $this->repository->count(new AuditEntryFilter(from: new DateTimeImmutable('2026-01-01T11:00:00+00:00'))));
        self::assertSame(2, $this->repository->count(new AuditEntryFilter(to: new DateTimeImmutable('2026-01-01T11:00:00+00:00'))));
        self::assertSame(0, $this->repository->count(new AuditEntryFilter(kind: AuditKind::Event)));
        self::assertSame(3, $this->repository->count(new AuditEntryFilter(kind: AuditKind::Command)));
    }

    public function testAnEntryCannotBeChanged(): void
    {
        $this->repository->add($this->entry(1, '2026-01-01T10:00:00+00:00', Actor::system()));

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('append-only');

        $this->connection->executeStatement("UPDATE audit.audit_entry SET name = 'Tampered'");
    }

    public function testAnEntryCannotBeDeleted(): void
    {
        $this->repository->add($this->entry(1, '2026-01-01T10:00:00+00:00', Actor::system()));

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('append-only');

        $this->connection->executeStatement('DELETE FROM audit.audit_entry');
    }
}
