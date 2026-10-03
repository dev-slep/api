<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence;

use App\Audit\Domain\Model\Actor;
use App\Audit\Domain\Model\ActorType;
use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Model\AuditEntryFilter;
use App\Audit\Domain\Model\AuditEntryId;
use App\Audit\Domain\Model\AuditKind;
use App\Audit\Domain\Model\Outcome;
use App\Audit\Domain\Repository\AuditEntryRepository;

use function assert;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

use function is_numeric;
use function is_scalar;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;

/**
 * Plain SQL on audit.audit_entry: the table is insert-only and has no ORM mapping, so writing never touches the
 * entity manager's unit of work (an entry can be written right after a failed command, whatever state it is in).
 */
final readonly class DbalAuditEntryRepository implements AuditEntryRepository
{
    private const string TIMESTAMP = 'Y-m-d H:i:s.uP';

    public function __construct(private Connection $connection)
    {
    }

    public function add(AuditEntry $entry): void
    {
        $this->connection->executeStatement(
            'INSERT INTO audit.audit_entry (id, occurred_at, kind, name, actor_type, actor_id, actor_role, target_id, payload, outcome, failure_reason, correlation_id, ip, user_agent)
             VALUES (:id, :occurredAt, :kind, :name, :actorType, :actorId, :actorRole, :targetId, CAST(:payload AS jsonb), :outcome, :failureReason, :correlationId, :ip, :userAgent)',
            [
                'id' => $entry->id->toString(),
                'occurredAt' => $entry->occurredAt->setTimezone(new DateTimeZone('UTC'))->format(self::TIMESTAMP),
                'kind' => $entry->kind->value,
                'name' => $entry->name,
                'actorType' => $entry->actor->type->value,
                'actorId' => $entry->actor->userId,
                'actorRole' => $entry->actor->role,
                'targetId' => $entry->targetId,
                'payload' => json_encode($entry->payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                'outcome' => $entry->outcome->value,
                'failureReason' => $entry->failureReason,
                'correlationId' => $entry->correlationId,
                'ip' => $entry->ip,
                'userAgent' => $entry->userAgent,
            ],
        );
    }

    public function search(AuditEntryFilter $filter, int $page, int $perPage): array
    {
        [$where, $params] = $this->where($filter);
        $params['limit'] = $perPage;
        $params['offset'] = ($page - 1) * $perPage;

        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM audit.audit_entry'.$where.' ORDER BY occurred_at DESC, id DESC LIMIT :limit OFFSET :offset',
            $params,
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function count(AuditEntryFilter $filter): int
    {
        [$where, $params] = $this->where($filter);
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM audit.audit_entry'.$where, $params);
        assert(is_numeric($count));

        return (int) $count;
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function where(AuditEntryFilter $filter): array
    {
        $criteria = [
            ['actor_id = :actorId', 'actorId', $filter->actorId],
            ['target_id = :targetId', 'targetId', $filter->targetId],
            ['name = :name', 'name', $filter->name],
            ['kind = :kind', 'kind', $filter->kind?->value],
            ['occurred_at >= :from', 'from', $filter->from?->format(self::TIMESTAMP)],
            ['occurred_at <= :to', 'to', $filter->to?->format(self::TIMESTAMP)],
        ];

        $conditions = [];
        $params = [];
        foreach ($criteria as [$condition, $parameter, $value]) {
            if (null !== $value) {
                $conditions[] = $condition;
                $params[$parameter] = $value;
            }
        }

        return [[] === $conditions ? '' : ' WHERE '.implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AuditEntry
    {
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($this->text($row['payload']), true, 512, JSON_THROW_ON_ERROR);

        return new AuditEntry(
            new AuditEntryId($this->text($row['id'])),
            new DateTimeImmutable($this->text($row['occurred_at'])),
            AuditKind::from($this->text($row['kind'])),
            $this->text($row['name']),
            $this->actor($row),
            $this->nullableText($row['target_id']),
            $payload ?? [],
            Outcome::from($this->text($row['outcome'])),
            $this->nullableText($row['failure_reason']),
            $this->nullableText($row['correlation_id']),
            $this->nullableText($row['ip']),
            $this->nullableText($row['user_agent']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function actor(array $row): Actor
    {
        return match (ActorType::from($this->text($row['actor_type']))) {
            ActorType::User => Actor::user($this->text($row['actor_id']), $this->nullableText($row['actor_role'])),
            ActorType::Anonymous => Actor::anonymous(),
            ActorType::System => Actor::system(),
        };
    }

    private function text(mixed $value): string
    {
        assert(is_scalar($value));

        return (string) $value;
    }

    private function nullableText(mixed $value): ?string
    {
        return null === $value ? null : $this->text($value);
    }
}
