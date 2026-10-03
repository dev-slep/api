<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit\Application;

use App\Audit\Application\ContractImplementation\AuditTrailFacade;
use App\Audit\Application\Query\ListAuditEntries;
use App\Audit\Application\Query\ListAuditEntriesHandler;
use App\Audit\Application\Service\AuditRecorder;
use App\Audit\Application\Service\PayloadExtractor;
use App\Audit\Domain\Model\Actor;
use App\Audit\Domain\Model\AuditKind;
use App\Audit\Domain\Model\Outcome;
use App\Audit\Domain\Policy\PayloadMasker;
use App\Audit\Infrastructure\Persistence\InMemoryAuditEntryRepository;
use App\SharedKernel\Contract\IntegrationEvent;
use App\Tests\Support\Fake\FrozenClock;
use App\Tests\Support\Fake\SequentialIdGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Stringable;

enum ExampleStatus: string
{
    case Open = 'open';
}

#[CoversClass(PayloadExtractor::class)]
#[CoversClass(AuditRecorder::class)]
#[CoversClass(AuditTrailFacade::class)]
#[CoversClass(ListAuditEntriesHandler::class)]
final class AuditApplicationTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';

    private InMemoryAuditEntryRepository $repository;
    private AuditRecorder $recorder;

    protected function setUp(): void
    {
        $this->repository = new InMemoryAuditEntryRepository();
        $this->recorder = new AuditRecorder($this->repository, new PayloadExtractor(), new PayloadMasker(), new FrozenClock('2026-01-01T12:00:00+00:00'), new SequentialIdGenerator());
    }

    private function event(string $name = 'example.happened.v1', string $id = 'event-1', ?string $accountId = self::USER): IntegrationEvent
    {
        return new readonly class($name, $id, $accountId) implements IntegrationEvent {
            public string $accountId;
            public ?string $ip;

            public function __construct(private string $name, private string $id, ?string $accountId)
            {
                $this->accountId = $accountId ?? '';
                $this->ip = '10.0.0.1';
            }

            public function eventId(): string
            {
                return $this->id;
            }

            public function eventName(): string
            {
                return $this->name;
            }

            public function version(): int
            {
                return 1;
            }

            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-01-01T11:59:00+00:00');
            }
        };
    }

    public function testThePayloadFlattensValueObjectsAndFindsTheTarget(): void
    {
        $message = new class {
            public string $accountId = 'acc-1';
            public ExampleStatus $status = ExampleStatus::Open;
            public DateTimeImmutable $at;
            public object $who;
            public object $opaque;
            public mixed $nothing = null;
            /** @var list<int> */
            public array $numbers = [1, 2];

            public function __construct()
            {
                $this->at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
                $this->who = new class implements Stringable {
                    public function __toString(): string
                    {
                        return 'someone';
                    }
                };
                $this->opaque = new class {
                    public ?object $deeper;

                    public function __construct()
                    {
                        $this->deeper = new class {
                            public object $deepest;

                            public function __construct()
                            {
                                $this->deepest = new class {
                                    public int $x = 1;
                                };
                            }
                        };
                    }
                };
            }
        };

        $payload = (new PayloadExtractor())->extract($message);

        self::assertSame('acc-1', $payload['accountId']);
        self::assertSame('open', $payload['status']);
        self::assertSame('2026-01-01T00:00:00+00:00', $payload['at']);
        self::assertSame('someone', $payload['who']);
        self::assertNull($payload['nothing']);
        self::assertSame([1, 2], $payload['numbers']);
        self::assertIsArray($payload['opaque']);
        self::assertSame('acc-1', (new PayloadExtractor())->targetId($payload));
        self::assertNull((new PayloadExtractor())->targetId(['email' => 'a@b.c', 'accountId' => null]));
        self::assertSame('x', (new PayloadExtractor())->targetId(['id' => 'x']));
    }

    public function testASuccessfulCommandIsRecordedWithItsMaskedPayload(): void
    {
        $command = new class {
            public string $email = 'a@b.c';
            public string $password = 'hunter2';
            public string $userId = 'u-1';
        };

        $this->recorder->recordCommand($command, Actor::user(self::USER, 'ROLE_ADMIN'), null, 'corr-1', '10.0.0.1', 'agent');

        $entry = $this->repository->all()[0];
        self::assertSame(AuditKind::Command, $entry->kind);
        self::assertSame(Outcome::Success, $entry->outcome);
        self::assertSame('u-1', $entry->targetId);
        self::assertSame('***', $entry->payload['password']);
        self::assertSame('a@b.c', $entry->payload['email']);
        self::assertSame('corr-1', $entry->correlationId);
        self::assertSame('10.0.0.1', $entry->ip);
        self::assertSame('agent', $entry->userAgent);
        self::assertSame('2026-01-01T12:00:00+00:00', $entry->occurredAt->format('c'));
        self::assertSame('01900000-0000-7000-8000-000000000001', $entry->id->toString());
    }

    public function testAFailedCommandKeepsTheReason(): void
    {
        $this->recorder->recordCommand(new class {}, Actor::anonymous(), str_repeat('x', 900), null, null, null);

        $entry = $this->repository->all()[0];
        self::assertSame(Outcome::Failure, $entry->outcome);
        self::assertSame(500, mb_strlen((string) $entry->failureReason));
    }

    public function testAnEventIsRecordedAtTheTimeItHappenedAttributedToItsAccount(): void
    {
        $this->recorder->recordEvent($this->event(), 'corr-2');

        $entry = $this->repository->all()[0];
        self::assertSame(AuditKind::Event, $entry->kind);
        self::assertSame('example.happened.v1', $entry->name);
        self::assertSame(Outcome::Recorded, $entry->outcome);
        self::assertSame(self::USER, $entry->actor->userId);
        self::assertSame(self::USER, $entry->targetId);
        self::assertSame('10.0.0.1', $entry->ip);
        self::assertSame('2026-01-01T11:59:00+00:00', $entry->occurredAt->format('c'));
    }

    public function testAnEventWithoutAnAccountIsAttributedToTheSystem(): void
    {
        $event = new readonly class implements IntegrationEvent {
            public function eventId(): string
            {
                return 'e';
            }

            public function eventName(): string
            {
                return 'x.y.v1';
            }

            public function version(): int
            {
                return 1;
            }

            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
        };

        $this->recorder->recordEvent($event, null);

        self::assertNull($this->repository->all()[0]->actor->userId);
    }

    public function testTheTrailPagesTheNewestEntriesFirst(): void
    {
        foreach (['a', 'b', 'c'] as $i => $name) {
            $this->recorder->recordEvent($this->event($name.'.v1', 'e'.$i), null);
        }
        $trail = new AuditTrailFacade($this->repository);

        $page = $trail->entries(page: 2, perPage: 2);

        self::assertSame(3, $page->total);
        self::assertSame(2, $page->page);
        self::assertCount(1, $page->items);
        self::assertSame(self::USER, $page->items[0]->actorId);
        self::assertSame('event', $page->items[0]->kind);
        self::assertSame('recorded', $page->items[0]->outcome);
    }

    public function testTheTrailClampsThePageSizeAndIgnoresAnUnknownKind(): void
    {
        $this->recorder->recordEvent($this->event(), null);
        $trail = new AuditTrailFacade($this->repository);

        $page = $trail->entries(kind: 'nonsense', page: 0, perPage: 5000);

        self::assertSame(1, $page->page);
        self::assertSame(100, $page->perPage);
        self::assertSame(1, $page->total);
        self::assertSame(0, $trail->entries(kind: 'command')->total);
        self::assertSame(1, $trail->entries(actorId: self::USER, name: 'example.happened.v1', targetId: self::USER)->total);
    }

    public function testTheQueryHandlerAsksTheTrail(): void
    {
        $this->recorder->recordEvent($this->event(), null);

        $page = (new ListAuditEntriesHandler(new AuditTrailFacade($this->repository)))(new ListAuditEntries(name: 'example.happened.v1', perPage: 10));

        self::assertSame(1, $page->total);
        self::assertSame(10, $page->perPage);
    }
}
