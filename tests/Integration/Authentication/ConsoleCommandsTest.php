<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Contract\Event\UserRegisteredV1;
use App\Authentication\Infrastructure\Console\CreateAdminCommand;
use App\Authentication\Infrastructure\Console\PurgeExpiredTokensCommand;
use App\Tests\Support\Attribute\CoversConsoleCommand;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\Authentication\TotpCodes;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CreateAdminCommand::class)]
#[CoversClass(PurgeExpiredTokensCommand::class)]
#[CoversConsoleCommand('app:auth:create-admin')]
#[CoversConsoleCommand('app:auth:purge-expired-tokens')]
final class ConsoleCommandsTest extends AuthenticationIntegrationTestCase
{
    private function tester(string $name): CommandTester
    {
        $application = new Application($this->client->getKernel());
        $application->setAutoExit(false);

        return new CommandTester($application->find($name));
    }

    // ---- app:auth:create-admin ----

    public function testCreatingAnAdminSavesTheAccountAndPrintsTheEnrolment(): void
    {
        $clock = $this->freezeClock();
        $email = $this->uniqueEmail('admin');
        $tester = $this->tester('app:auth:create-admin');
        $tester->setInputs(['a long admin passphrase']);

        $status = $tester->execute(['email' => strtoupper($email)]);

        self::assertSame(0, $status, $tester->getDisplay());
        $row = $this->accountRow($email);
        self::assertNotNull($row);
        self::assertSame('ADMIN', $row['role']);
        self::assertNotNull($row['email_verified_at']);
        self::assertIsString($row['password_hash']);
        self::assertStringStartsWith('$argon2id$', $row['password_hash']);
        self::assertIsString($row['id']);
        $output = $tester->getDisplay();
        self::assertStringContainsString('Admin created: '.$row['id'], $output);
        self::assertSame(1, preg_match('/Manual entry secret: ([A-Z2-7]{32})/', $output, $matches));
        self::assertStringContainsString('otpauth://totp/Slep', $output);
        self::assertStringNotContainsString('a long admin passphrase', $output);
        self::assertSame(1, $this->countRows('authentication.two_factor_secret', 'account_id = :id AND confirmed_at IS NULL', ['id' => $row['id']]));
        $this->assertEventStored(UserRegisteredV1::class);

        // The printed secret really is the enrolled one
        $code = TotpCodes::at($matches[1], $clock->now()->getTimestamp());
        $login = $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => 'a long admin passphrase']);
        $pending = $this->jsonString('accessToken', $login);
        self::assertSame(200, $this->call('POST', '/api/v1/admin/auth/2fa/enrol/confirm', ['code' => $code], $this->bearer($pending))->getStatusCode());
    }

    public function testThePasswordCanBePipedIn(): void
    {
        $email = $this->uniqueEmail('admin');
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, "a piped admin passphrase\n");
        rewind($stream);
        $application = new Application($this->client->getKernel());
        $command = $application->find('app:auth:create-admin');
        $input = new \Symfony\Component\Console\Input\ArrayInput(['email' => $email, '--password-from-stdin' => true]);
        $input->setStream($stream);

        $status = $command->run($input, new \Symfony\Component\Console\Output\BufferedOutput());

        self::assertSame(0, $status);
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => 'a piped admin passphrase'])->getStatusCode());
    }

    public function testCreatingTheSameAdminTwiceFailsWithoutDuplicates(): void
    {
        $email = $this->uniqueEmail('admin');
        $first = $this->tester('app:auth:create-admin');
        $first->setInputs(['a long admin passphrase']);
        self::assertSame(0, $first->execute(['email' => $email]));

        $second = $this->tester('app:auth:create-admin');
        $second->setInputs(['a long admin passphrase']);
        $status = $second->execute(['email' => $email]);

        self::assertSame(1, $status);
        self::assertStringContainsString('already exists', $second->getDisplay());
        self::assertSame(1, $this->countRows('authentication.user_account', 'email = :email', ['email' => $email]));
    }

    public function testAWeakPasswordOrAnInvalidEmailIsRefused(): void
    {
        $tester = $this->tester('app:auth:create-admin');
        $tester->setInputs(['short']);
        self::assertSame(1, $tester->execute(['email' => $this->uniqueEmail('admin')]));
        self::assertStringContainsString('at least 10 characters', $tester->getDisplay());

        $invalid = $this->tester('app:auth:create-admin');
        $invalid->setInputs(['a long admin passphrase']);
        self::assertSame(1, $invalid->execute(['email' => 'not an email']));
        self::assertSame(0, $this->countRows('authentication.user_account', "role = 'ADMIN'"));
    }

    public function testNoPasswordAtAllIsInvalidInput(): void
    {
        $tester = $this->tester('app:auth:create-admin');
        $tester->setInputs(['']);

        self::assertSame(2, $tester->execute(['email' => $this->uniqueEmail('admin')]));
    }

    public function testTheEmailArgumentIsRequired(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);

        $this->tester('app:auth:create-admin')->execute([]);
    }

    // ---- app:auth:purge-expired-tokens ----

    private function insertRefreshToken(string $label, string $expiresAt): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO authentication.refresh_token (id, family_id, account_id, hash, issued_at, expires_at, version) VALUES (:id, :id, :id, :hash, :issued, :expires, 1)',
            ['id' => $this->uuid(), 'hash' => hash('sha256', $label), 'issued' => '2026-01-01T00:00:00+00:00', 'expires' => $expiresAt],
        );
    }

    private function insertOneTimeToken(string $label, string $expiresAt): void
    {
        $this->connection()->executeStatement(
            "INSERT INTO authentication.one_time_token (id, account_id, purpose, hash, issued_at, expires_at) VALUES (:id, :id, 'PASSWORD_RESET', :hash, :issued, :expires)",
            ['id' => $this->uuid(), 'hash' => hash('sha256', $label), 'issued' => '2026-01-01T00:00:00+00:00', 'expires' => $expiresAt],
        );
    }

    private function uuid(): string
    {
        return \Symfony\Component\Uid\Uuid::v7()->toRfc4122();
    }

    public function testThePurgeDeletesTokensExpiredMoreThanThirtyDaysAgoAndKeepsTheRest(): void
    {
        $this->freezeClock('2026-03-01T00:00:00+00:00');
        $this->insertRefreshToken('long gone', '2026-01-01T00:00:00+00:00');
        $this->insertRefreshToken('exactly at the cutoff', '2026-01-30T00:00:00+00:00');
        $this->insertRefreshToken('one second after the cutoff', '2026-01-30T00:00:01+00:00');
        $this->insertRefreshToken('still valid', '2026-04-01T00:00:00+00:00');
        $this->insertOneTimeToken('reset long gone', '2026-01-02T00:00:00+00:00');
        $this->insertOneTimeToken('reset recent', '2026-02-28T00:00:00+00:00');
        $tester = $this->tester('app:auth:purge-expired-tokens');

        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertStringContainsString('Deleted 2 expired token(s).', $tester->getDisplay());
        self::assertSame(3, $this->countRows('authentication.refresh_token'), 'the token that expired exactly at the cutoff is kept');
        self::assertSame(1, $this->countRows('authentication.one_time_token'));
    }

    public function testThePurgeWithNothingToDoSucceedsAndRunningItTwiceChangesNothing(): void
    {
        $this->freezeClock('2026-03-01T00:00:00+00:00');
        $this->insertRefreshToken('long gone', '2026-01-01T00:00:00+00:00');

        $first = $this->tester('app:auth:purge-expired-tokens');
        self::assertSame(0, $first->execute([]));
        self::assertStringContainsString('Deleted 1', $first->getDisplay());

        $second = $this->tester('app:auth:purge-expired-tokens');
        self::assertSame(0, $second->execute([]));
        self::assertStringContainsString('Deleted 0', $second->getDisplay());
    }

    public function testTheScheduleRunsThePurgeDaily(): void
    {
        $schedule = static::getContainer()->get(\App\SharedKernel\Infrastructure\Scheduler\DefaultSchedule::class);

        $messages = array_map(static fn ($recurring): array => iterator_to_array($recurring->getProvider()->getMessages(new \Symfony\Component\Scheduler\Generator\MessageContext('default', 'x', $recurring->getTrigger(), new DateTimeImmutable()))), $schedule->getSchedule()->getRecurringMessages());
        $flat = array_merge(...array_values($messages));

        self::assertContains(\App\Authentication\Application\Command\PurgeExpiredTokens::class, array_map(static fn (object $message): string => $message::class, $flat));
    }
}
