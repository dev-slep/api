<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit\Domain;

use App\Audit\Domain\Model\Actor;
use App\Audit\Domain\Model\ActorType;
use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Model\AuditEntryFilter;
use App\Audit\Domain\Model\AuditEntryId;
use App\Audit\Domain\Model\AuditKind;
use App\Audit\Domain\Model\Outcome;
use App\Audit\Domain\Policy\PayloadMasker;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Actor::class)]
#[CoversClass(AuditEntry::class)]
#[CoversClass(AuditEntryFilter::class)]
#[CoversClass(PayloadMasker::class)]
final class AuditDomainTest extends TestCase
{
    private const string USER = '01900000-0000-7000-8000-0000000000aa';

    private function entry(string $name = 'Login', ?string $actorId = self::USER, string $when = '2026-01-02T10:00:00+00:00', AuditKind $kind = AuditKind::Command, ?string $target = 'target-1'): AuditEntry
    {
        return new AuditEntry(
            new AuditEntryId('01900000-0000-7000-8000-000000000001'),
            new DateTimeImmutable($when),
            $kind,
            $name,
            null === $actorId ? Actor::system() : Actor::user($actorId, 'ROLE_ADMIN'),
            $target,
            [],
            Outcome::Success,
            null,
            null,
            null,
            null,
        );
    }

    public function testActorsKnowWhoTheyAre(): void
    {
        self::assertSame(ActorType::User, Actor::user(self::USER, 'ROLE_DRIVER')->type);
        self::assertSame(self::USER, Actor::user(self::USER, null)->userId);
        self::assertSame(ActorType::Anonymous, Actor::anonymous()->type);
        self::assertNull(Actor::anonymous()->userId);
        self::assertSame(ActorType::System, Actor::system()->type);
    }

    public function testAUserActorNeedsAnId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Actor::user('', null);
    }

    public function testAnEmptyFilterMatchesEverything(): void
    {
        self::assertTrue((new AuditEntryFilter())->matches($this->entry()));
    }

    public function testTheCriteriaAreCombinedWithAnd(): void
    {
        $entry = $this->entry();

        self::assertTrue((new AuditEntryFilter(self::USER, 'target-1', 'Login', AuditKind::Command, new DateTimeImmutable('2026-01-02T09:00:00+00:00'), new DateTimeImmutable('2026-01-02T11:00:00+00:00')))->matches($entry));
        self::assertFalse((new AuditEntryFilter('01900000-0000-7000-8000-0000000000bb'))->matches($entry));
        self::assertFalse((new AuditEntryFilter(targetId: 'other'))->matches($entry));
        self::assertFalse((new AuditEntryFilter(name: 'Logout'))->matches($entry));
        self::assertFalse((new AuditEntryFilter(kind: AuditKind::Event))->matches($entry));
        self::assertFalse((new AuditEntryFilter(from: new DateTimeImmutable('2026-01-02T10:00:01+00:00')))->matches($entry));
        self::assertFalse((new AuditEntryFilter(to: new DateTimeImmutable('2026-01-02T09:59:59+00:00')))->matches($entry));
    }

    public function testSecretsAreMaskedAtEveryDepthWhateverTheCase(): void
    {
        $masked = (new PayloadMasker())->mask([
            'email' => 'a@b.c',
            'password' => 'hunter2',
            'refreshToken' => 'abc',
            'nested' => ['TwoFactorCode' => '123456', 'city' => 'Novi Sad', 'deeper' => ['secret' => 'x']],
            'list' => [['apiKey' => 'k']],
            'passwordHash' => null,
        ]);

        self::assertSame([
            'email' => 'a@b.c',
            'password' => '***',
            'refreshToken' => '***',
            'nested' => ['TwoFactorCode' => '***', 'city' => 'Novi Sad', 'deeper' => ['secret' => '***']],
            'list' => [['apiKey' => '***']],
            'passwordHash' => null,
        ], $masked);
    }
}
