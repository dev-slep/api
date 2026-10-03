<?php

declare(strict_types=1);

namespace App\Tests\Integration\Audit;

use App\Audit\Domain\Model\Actor;
use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Model\AuditEntryId;
use App\Audit\Domain\Model\AuditKind;
use App\Audit\Domain\Model\Outcome;
use App\Audit\Domain\Repository\AuditEntryRepository;
use App\Audit\Infrastructure\Http\Controller\ListAuditEntriesController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\Authentication\TotpCodes;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;

use function sprintf;

use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ListAuditEntriesController::class)]
#[CoversEndpoint('GET', '/api/v1/admin/audit')]
final class AuditEndpointTest extends AuthenticationIntegrationTestCase
{
    private const string ACTOR = '01900000-0000-7000-8000-0000000000aa';

    private function adminToken(): string
    {
        $clock = $this->freezeClock('2026-01-01T12:00:10+00:00');
        $admin = $this->createAdminWithSecondFactor();
        $clock->advance('+30 seconds');
        $pending = $this->jsonString('accessToken', $this->call('POST', '/api/v1/auth/login', ['email' => $admin['email'], 'password' => self::PASSWORD]));
        $code = TotpCodes::at($admin['secret'], $clock->now()->getTimestamp());

        return $this->jsonString('accessToken', $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $code], $this->bearer($pending)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(Response $response): array
    {
        $items = [];
        foreach ($this->jsonList('items', $response) as $item) {
            self::assertIsArray($item);
            $keyed = [];
            foreach ($item as $key => $value) {
                $keyed[(string) $key] = $value;
            }
            $items[] = $keyed;
        }

        return $items;
    }

    private function seed(int $n, string $name, string $when, string $actor = self::ACTOR, ?string $target = null, AuditKind $kind = AuditKind::Command): void
    {
        $log = static::getContainer()->get(AuditEntryRepository::class);
        $log->add(new AuditEntry(
            new AuditEntryId(sprintf('01900000-0000-7000-8000-%012d', $n)),
            new DateTimeImmutable($when),
            $kind,
            $name,
            Actor::user($actor, 'ROLE_DRIVER'),
            $target,
            [],
            Outcome::Success,
            null,
            null,
            null,
            null,
        ));
    }

    public function testAnonymousRequestsAreRefused(): void
    {
        self::assertSame(401, $this->call('GET', '/api/v1/admin/audit')->getStatusCode());
    }

    public function testAnAdminWhoHasNotFinishedTheSecondFactorMayNotReadTheLog(): void
    {
        $clock = $this->freezeClock('2026-01-01T12:00:10+00:00');
        $admin = $this->createAdminWithSecondFactor();
        $clock->advance('+30 seconds');
        $pending = $this->jsonString('accessToken', $this->call('POST', '/api/v1/auth/login', ['email' => $admin['email'], 'password' => self::PASSWORD]));

        self::assertSame(403, $this->call('GET', '/api/v1/admin/audit', headers: $this->bearer($pending))->getStatusCode());
    }

    public function testADriverMayNotReadTheLog(): void
    {
        $driver = $this->login($this->createAccount()->email()->toString());

        self::assertSame(403, $this->call('GET', '/api/v1/admin/audit', headers: $this->bearer($driver['accessToken']))->getStatusCode());
    }

    public function testAnAdminReadsThePagesNewestFirstAndFiltersThem(): void
    {
        $token = $this->adminToken();
        $this->seed(1, 'PlaceBid', '2020-01-01T10:00:00+00:00', target: 'bid-1');
        $this->seed(2, 'PlaceBid', '2020-01-01T11:00:00+00:00', actor: '01900000-0000-7000-8000-0000000000bb');
        $this->seed(3, 'authentication.user_logged_in.v1', '2020-01-01T12:00:00+00:00', kind: AuditKind::Event);

        $all = $this->call('GET', '/api/v1/admin/audit?from=2020-01-01T00:00:00Z&to=2020-01-02T00:00:00Z', headers: $this->bearer($token));

        self::assertSame(200, $all->getStatusCode(), (string) $all->getContent());
        self::assertMatchesOpenApiSchema($all, 'GET', '/api/v1/admin/audit');
        $body = $this->json();
        self::assertSame(3, $body['total']);
        self::assertSame(['authentication.user_logged_in.v1', 'PlaceBid', 'PlaceBid'], array_column($this->items($all), 'name'));
        self::assertSame(1, $body['page']);
        self::assertSame(50, $body['perPage']);

        $byActor = $this->call('GET', '/api/v1/admin/audit?actorId='.self::ACTOR.'&from=2020-01-01T00:00:00Z&to=2020-01-02T00:00:00Z', headers: $this->bearer($token));
        self::assertSame(2, $this->json($byActor)['total']);

        $byTarget = $this->call('GET', '/api/v1/admin/audit?targetId=bid-1&name=PlaceBid&kind=command', headers: $this->bearer($token));
        self::assertSame(1, $this->json($byTarget)['total']);
        self::assertSame(self::ACTOR, $this->items($byTarget)[0]['actorId']);
        self::assertStringContainsString('"payload":{}', (string) $byTarget->getContent(), 'an empty payload is an object, not a list');

        $paged = $this->call('GET', '/api/v1/admin/audit?name=PlaceBid&perPage=1&page=2', headers: $this->bearer($token));
        self::assertSame(2, $this->json($paged)['total']);
        self::assertCount(1, $this->items($paged));
        self::assertSame('01900000-0000-7000-8000-000000000001', $this->items($paged)[0]['id']);
    }

    public function testBadFiltersAreRejected(): void
    {
        $token = $this->adminToken();

        foreach (['actorId=nope', 'kind=other', 'perPage=101', 'page=0'] as $query) {
            self::assertSame(422, $this->call('GET', '/api/v1/admin/audit?'.$query, headers: $this->bearer($token))->getStatusCode(), $query);
        }
    }

    public function testARegistrationIsAuditedWithoutThePasswordAndAttributedToNobody(): void
    {
        $token = $this->adminToken();
        $email = $this->uniqueEmail('new');
        self::assertSame(201, $this->call('POST', '/api/v1/auth/register', ['email' => $email, 'password' => self::PASSWORD, 'role' => 'DRIVER', 'locale' => 'sr_Latn'], ['Idempotency-Key' => 'audit-register-1'])->getStatusCode());

        $response = $this->call('GET', '/api/v1/admin/audit?name=RegisterUser', headers: $this->bearer($token));

        $entry = $this->items($response)[0];
        self::assertSame('anonymous', $entry['actorType']);
        self::assertSame('success', $entry['outcome']);
        self::assertIsArray($entry['payload']);
        self::assertSame($email, $entry['payload']['email']);
        self::assertSame('***', $entry['payload']['password']);
        self::assertNotNull($entry['correlationId']);
        self::assertStringNotContainsString(self::PASSWORD, (string) $response->getContent());
    }
}
