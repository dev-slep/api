<?php

declare(strict_types=1);

namespace App\Tests\Application\Audit;

use App\Audit\Application\ContractImplementation\AuditTrailFacade;
use App\Audit\Contract\AuditTrail;
use App\Authentication\Application\Command\RegisterUser;
use App\SharedKernel\Application\CommandBus;
use App\Tests\Support\Attribute\CoversContractMethod;
use App\Tests\Support\Authentication\AuthenticationApplicationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(AuditTrailFacade::class)]
#[CoversContractMethod(AuditTrail::class, 'entries')]
final class AuditTrailContractTest extends AuthenticationApplicationTestCase
{
    private function trail(): AuditTrail
    {
        $trail = static::getContainer()->get(AuditTrail::class);

        return $trail;
    }

    private function register(string $email): void
    {
        $bus = static::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterUser($email, 'correct horse battery', 'TOWER', null, 'sr_Latn'));
    }

    public function testEveryCommandOnTheBusLeavesAnEntryWithoutItsSecrets(): void
    {
        $this->register('ana@example.com');

        $page = $this->trail()->entries(name: 'RegisterUser');

        self::assertSame(1, $page->total);
        $entry = $page->items[0];
        self::assertSame('command', $entry->kind);
        self::assertSame('success', $entry->outcome);
        self::assertSame('system', $entry->actorType, 'no HTTP request and nobody signed in');
        self::assertSame('ana@example.com', $entry->payload['email']);
        self::assertSame('***', $entry->payload['password']);
        self::assertNotNull($entry->correlationId);
    }

    public function testAFailedCommandIsRecordedAsAFailure(): void
    {
        $this->register('ana@example.com');
        try {
            $this->register('ana@example.com');
            self::fail('Registering the same email twice should have been refused.');
        } catch (Throwable) {
        }

        $page = $this->trail()->entries(name: 'RegisterUser');

        self::assertSame(2, $page->total);
        self::assertSame('failure', $page->items[0]->outcome, 'newest first');
        self::assertNotNull($page->items[0]->failureReason);
    }

    public function testTheTrailFiltersByTimeAndPages(): void
    {
        $this->register('a@example.com');
        $this->register('b@example.com');
        $this->register('c@example.com');
        $now = $this->frozenClock->now();

        self::assertSame(3, $this->trail()->entries(from: $now, to: $now)->total);
        self::assertSame(0, $this->trail()->entries(from: $now->modify('+1 second'))->total);
        self::assertSame(0, $this->trail()->entries(to: $now->modify('-1 second'))->total);
        self::assertCount(2, $this->trail()->entries(perPage: 2)->items);
        self::assertCount(1, $this->trail()->entries(page: 2, perPage: 2)->items);
    }
}
