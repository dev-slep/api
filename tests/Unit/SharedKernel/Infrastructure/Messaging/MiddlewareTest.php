<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Infrastructure\Messaging\AuditMiddleware;
use App\SharedKernel\Infrastructure\Messaging\NullCommandAuditor;
use App\SharedKernel\Infrastructure\Messaging\TransactionMiddleware;
use App\SharedKernel\Infrastructure\Persistence\DoctrineTransaction;
use App\Tests\Support\Fake\NullTransaction;
use App\Tests\Support\Fake\RecordingCommandAuditor;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;
use App\Tests\Support\Fixtures\Messaging\RecordingCommand;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Throwable;

#[CoversClass(AuditMiddleware::class)]
#[CoversClass(NullCommandAuditor::class)]
#[CoversClass(TransactionMiddleware::class)]
#[CoversClass(DoctrineTransaction::class)]
final class MiddlewareTest extends TestCase
{
    public function testAuditRecordsSuccessAndReturnsTheEnvelope(): void
    {
        $auditor = new RecordingCommandAuditor();
        $command = new RecordingCommand('x');
        $envelope = new Envelope($command);

        $result = new AuditMiddleware($auditor)->handle($envelope, $this->stackReturning($envelope));

        self::assertSame($envelope, $result);
        self::assertCount(1, $auditor->entries);
        self::assertSame('success', $auditor->entries[0]['outcome']);
        self::assertSame($command, $auditor->entries[0]['command']);
    }

    public function testAuditRecordsFailureAndRethrows(): void
    {
        $auditor = new RecordingCommandAuditor();
        $failure = new DomainException('nope');
        $command = new RecordingCommand('x');

        try {
            new AuditMiddleware($auditor)->handle(new Envelope($command), $this->stackThrowing($failure));
            self::fail('Expected the failure to be rethrown');
        } catch (Throwable $thrown) {
            self::assertSame($failure, $thrown);
        }

        self::assertCount(1, $auditor->entries);
        self::assertSame('failure', $auditor->entries[0]['outcome']);
        self::assertSame($failure, $auditor->entries[0]['failure']);
    }

    public function testAuditIgnoresMessagesThatAreNotCommands(): void
    {
        $auditor = new RecordingCommandAuditor();
        $envelope = new Envelope(new FixtureIntegrationEvent());

        $result = new AuditMiddleware($auditor)->handle($envelope, $this->stackReturning($envelope));

        self::assertSame($envelope, $result);
        self::assertSame([], $auditor->entries);
    }

    public function testNullAuditorDoesNothing(): void
    {
        $auditor = new NullCommandAuditor();

        $auditor->recordSuccess(new RecordingCommand('x'));
        $auditor->recordFailure(new RecordingCommand('x'), new RuntimeException());

        $this->expectNotToPerformAssertions();
    }

    public function testTransactionMiddlewareRunsTheRestOfTheChainInsideTheTransaction(): void
    {
        $transaction = new NullTransaction();
        $envelope = new Envelope(new RecordingCommand('x'));

        $result = new TransactionMiddleware($transaction)->handle($envelope, $this->stackReturning($envelope));

        self::assertSame($envelope, $result);
        self::assertSame(1, $transaction->runs);
    }

    public function testTransactionMiddlewarePropagatesFailures(): void
    {
        $failure = new DomainException('nope');

        $this->expectExceptionObject($failure);

        new TransactionMiddleware(new NullTransaction())->handle(new Envelope(new RecordingCommand('x')), $this->stackThrowing($failure));
    }

    public function testDoctrineTransactionDelegatesToTheEntityManager(): void
    {
        $entityManager = self::createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $work): mixed => $work());

        $calls = 0;

        $result = new DoctrineTransaction($entityManager)->run(static function () use (&$calls): int {
            return ++$calls;
        });

        self::assertSame(1, $result);
    }

    private function stackReturning(Envelope $envelope): StackInterface
    {
        $next = self::createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturn($envelope);

        $stack = self::createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }

    private function stackThrowing(Throwable $failure): StackInterface
    {
        $next = self::createStub(MiddlewareInterface::class);
        $next->method('handle')->willThrowException($failure);

        $stack = self::createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }
}
