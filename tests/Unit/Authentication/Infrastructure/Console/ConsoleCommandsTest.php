<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Console;

use App\Authentication\Application\Command\CreateAdmin;
use App\Authentication\Application\Command\PurgeExpiredTokens;
use App\Authentication\Application\Command\Result\CreatedAdmin;
use App\Authentication\Application\Command\Result\TwoFactorEnrolment;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Infrastructure\Console\CreateAdminCommand;
use App\Authentication\Infrastructure\Console\PurgeExpiredTokensCommand;
use App\Tests\Support\Authentication\RecordingCommandBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Throwable;

#[CoversClass(CreateAdminCommand::class)]
#[CoversClass(PurgeExpiredTokensCommand::class)]
final class ConsoleCommandsTest extends TestCase
{
    private function bus(mixed $result, ?Throwable $failure = null): RecordingCommandBus
    {
        return $this->bus = new RecordingCommandBus($result, $failure);
    }

    private RecordingCommandBus $bus;

    private function tester(ConsoleCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find((string) $command->getName()));
    }

    private function created(): CreatedAdmin
    {
        return new CreatedAdmin('01900000-0000-7000-8000-000000000001', new TwoFactorEnrolment('JBSWY3DPEHPK3PXP', 'otpauth://totp/Slep%3Aroot%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=Slep'));
    }

    public function testAnAdminIsCreatedFromAHiddenPasswordPrompt(): void
    {
        $tester = $this->tester(new CreateAdminCommand($this->bus($this->created())));
        $tester->setInputs(['a long admin passphrase']);

        $status = $tester->execute(['email' => 'root@example.com']);

        self::assertSame(0, $status);
        self::assertEquals([new CreateAdmin('root@example.com', 'a long admin passphrase')], $this->bus->dispatched);
        $output = $tester->getDisplay();
        self::assertStringContainsString('Admin created: 01900000-0000-7000-8000-000000000001', $output);
        self::assertStringContainsString('otpauth://totp/Slep', $output);
        self::assertStringContainsString('JBSWY3DPEHPK3PXP', $output);
        self::assertStringNotContainsString('a long admin passphrase', $output);
    }

    public function testThePasswordCanComeFromStandardInput(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, "piped passphrase\n");
        rewind($stream);
        $input = new \Symfony\Component\Console\Input\ArrayInput(['email' => 'root@example.com', '--password-from-stdin' => true]);
        $input->setStream($stream);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $command = new CreateAdminCommand($this->bus($this->created()));
        $command->setApplication(new Application());

        $status = $command->run($input, $output);

        self::assertSame(0, $status);
        $dispatched = $this->bus->dispatched[0] ?? null;
        self::assertInstanceOf(CreateAdmin::class, $dispatched);
        self::assertSame('piped passphrase', $dispatched->password);
    }

    public function testAnEmptyPasswordIsRefusedBeforeAnythingIsDispatched(): void
    {
        $tester = $this->tester(new CreateAdminCommand($this->bus($this->created())));
        $tester->setInputs(['']);

        $status = $tester->execute(['email' => 'root@example.com']);

        self::assertSame(ConsoleCommand::INVALID, $status);
        self::assertSame([], $this->bus->dispatched);
        self::assertStringContainsString('No password given', $tester->getDisplay());
    }

    public function testADomainRefusalIsShownWithoutAStackTrace(): void
    {
        $tester = $this->tester(new CreateAdminCommand($this->bus(null, AuthenticationProblem::emailAlreadyRegistered())));
        $tester->setInputs(['a long admin passphrase']);

        $status = $tester->execute(['email' => 'root@example.com']);

        self::assertSame(ConsoleCommand::FAILURE, $status);
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testAnUnexpectedFailureIsReportedAndRethrown(): void
    {
        $tester = $this->tester(new CreateAdminCommand($this->bus(null, new RuntimeException('database is down'))));
        $tester->setInputs(['a long admin passphrase']);

        try {
            $tester->execute(['email' => 'root@example.com']);
            self::fail('The failure was swallowed.');
        } catch (RuntimeException $failure) {
            self::assertSame('database is down', $failure->getMessage());
        }
    }

    public function testThePurgeReportsHowManyTokensWereDeleted(): void
    {
        $tester = $this->tester(new PurgeExpiredTokensCommand($this->bus(7), new LockFactory(new InMemoryStore())));

        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertStringContainsString('Deleted 7 expired token(s).', $tester->getDisplay());
        self::assertEquals([new PurgeExpiredTokens()], $this->bus->dispatched);
    }

    public function testThePurgeWithNothingToDoSucceeds(): void
    {
        $tester = $this->tester(new PurgeExpiredTokensCommand($this->bus(0), new LockFactory(new InMemoryStore())));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Deleted 0 expired token(s).', $tester->getDisplay());
    }

    public function testASecondPurgeWhileTheFirstHoldsTheLockDoesNothing(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $held = $factory->createLock('auth_purge_expired_tokens', 300);
        self::assertTrue($held->acquire());
        $tester = $this->tester(new PurgeExpiredTokensCommand($this->bus(7), $factory));

        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertSame([], $this->bus->dispatched);
        self::assertStringContainsString('already running', $tester->getDisplay());
    }

    public function testThePurgeReleasesItsLockEvenWhenItFails(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $failing = $this->tester(new PurgeExpiredTokensCommand($this->bus(null, new RuntimeException('boom')), $factory));

        try {
            $failing->execute([]);
            self::fail('The failure was swallowed.');
        } catch (RuntimeException) {
        }

        self::assertTrue($factory->createLock('auth_purge_expired_tokens', 300)->acquire(), 'the lock was released');
    }

    public function testTheCommandsAreNamedAsDocumented(): void
    {
        self::assertSame('slep:auth:create-admin', (new CreateAdminCommand($this->bus(null)))->getName());
        self::assertSame('slep:auth:purge-expired-tokens', (new PurgeExpiredTokensCommand($this->bus(null), new LockFactory(new InMemoryStore())))->getName());
    }
}
