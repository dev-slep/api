<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\Authentication\Application\Command\Result\TwoFactorEnrolment;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Domain\Policy\PasswordHasher;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;
use App\Tests\Support\Fake\FrozenClock;
use App\Tests\Support\IntegrationTestCase;

use function assert;

use Doctrine\DBAL\Connection;

use function is_string;
use function sprintf;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Integration tests of the Authentication endpoints: real Postgres (rolled back after every test), real Messenger
 * transport, real mail delivery to Mailpit; third parties through WireMock.
 */
abstract class AuthenticationIntegrationTestCase extends IntegrationTestCase
{
    use ReadsJsonResponses;

    protected const string PASSWORD = 'correct horse battery';

    protected function setUp(): void
    {
        parent::setUp();

        // One kernel for the whole test, so the clock, rate limiters and fakes survive between requests
        $this->client->disableReboot();
    }

    protected function connection(): Connection
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }

    /**
     * Changes rows behind the application's back and forgets what the entity manager had loaded, as another
     * process would have done.
     *
     * @param array<string, mixed> $params
     */
    protected function rawUpdate(string $sql, array $params = []): void
    {
        $this->ensureOpenEntityManager();
        $this->connection()->executeStatement($sql, $params);
        $manager = static::getContainer()->get('doctrine.orm.entity_manager');
        $manager->clear();
    }

    protected function commandBus(): CommandBus
    {
        $bus = static::getContainer()->get(CommandBus::class);

        return $bus;
    }

    protected function freezeClock(string $now = '2026-01-01T12:00:00+00:00'): FrozenClock
    {
        $clock = new FrozenClock($now);
        static::getContainer()->set(Clock::class, $clock);

        return $clock;
    }

    protected function uniqueEmail(string $name = 'user'): string
    {
        return sprintf('%s.%s@example.com', $name, bin2hex(random_bytes(6)));
    }

    /**
     * Requests without an `Accept-Language` header get Serbian, the application default (BrowserKit would add "en").
     */
    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    protected function call(string $method, string $uri, array $body = [], array $headers = []): Response
    {
        return $this->jsonRequest($method, $uri, $body, $headers + ['Accept-Language' => '']);
    }

    /**
     * Saves an account straight to the database, skipping registration.
     */
    /**
     * A request that fails inside a command closes the entity manager (as in production, where the process ends).
     * Tests that keep going afterwards get a fresh one.
     */
    protected function ensureOpenEntityManager(): void
    {
        $manager = static::getContainer()->get('doctrine.orm.entity_manager');
        if (!$manager->isOpen()) {
            $registry = static::getContainer()->get('doctrine');
            $registry->resetManager();
        }
    }

    protected function createAccount(?string $email = null, AccountRole $role = AccountRole::Driver, bool $verified = true, string $password = self::PASSWORD): UserAccount
    {
        $this->ensureOpenEntityManager();
        $container = static::getContainer();
        $hasher = $container->get(PasswordHasher::class);
        $ids = $container->get(IdGenerator::class);
        $repository = $container->get(UserAccountRepository::class);
        $clock = $container->get(Clock::class);

        $email = new Email($email ?? $this->uniqueEmail());
        $hash = $hasher->hash(new PlainPassword($password));
        $id = new AccountId($ids->generate());
        $account = AccountRole::Admin === $role
            ? UserAccount::createAdmin($id, $email, $hash, new Locale('en'), $clock->now())
            : UserAccount::registerWithPassword($id, $email, $hash, $role, null, new Locale('en'), $clock->now());
        if ($verified) {
            $account->verifyEmail($clock->now());
        }
        $repository->save($account);
        $account->releaseEvents();

        return $account;
    }

    /**
     * @return array{accessToken: string, refreshToken: string, expiresIn: int, tokenType: string}
     */
    protected function login(string $email, string $password = self::PASSWORD): array
    {
        $response = $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => $password]);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        /** @var array{accessToken: string, refreshToken: string, expiresIn: int, tokenType: string} $body */
        $body = $this->json($response);

        return $body;
    }

    /**
     * Creates an admin with a confirmed second factor and returns what a test needs to log in as that admin.
     *
     * @return array{email: string, secret: non-empty-string, recoveryCodes: list<string>, id: string}
     */
    protected function createAdminWithSecondFactor(): array
    {
        $email = $this->uniqueEmail('admin');
        $created = $this->commandBus()->dispatch(new \App\Authentication\Application\Command\CreateAdmin($email, self::PASSWORD));
        assert($created instanceof \App\Authentication\Application\Command\Result\CreatedAdmin);

        $clock = static::getContainer()->get(Clock::class);
        $codes = $this->commandBus()->dispatch(new \App\Authentication\Application\Command\ConfirmTwoFactorEnrolment($created->accountId, $this->totp($created->enrolment, $clock)));
        assert($codes instanceof \App\Authentication\Application\Command\Result\RecoveryCodes);

        $secret = $created->enrolment->secret;
        assert('' !== $secret);

        return ['email' => $email, 'secret' => $secret, 'recoveryCodes' => $codes->codes, 'id' => $created->accountId];
    }

    protected function totp(TwoFactorEnrolment $enrolment, Clock $clock, int $secondsFromNow = 0): string
    {
        assert('' !== $enrolment->secret);

        return TotpCodes::at($enrolment->secret, $clock->now()->getTimestamp() + $secondsFromNow);
    }

    // ---- database and events ----

    /**
     * @return array<string, mixed>|null
     */
    protected function accountRow(string $email): ?array
    {
        $row = $this->connection()->fetchAssociative('SELECT * FROM authentication.user_account WHERE email = :email', ['email' => mb_strtolower($email)]);

        return false === $row ? null : $row;
    }

    /**
     * One column of an account's row; the account has to exist.
     */
    protected function accountColumn(string $email, string $column): mixed
    {
        $row = $this->accountRow($email);
        self::assertNotNull($row, "No account for $email");
        self::assertArrayHasKey($column, $row);

        return $row[$column];
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function countRows(string $table, string $where = 'TRUE', array $params = []): int
    {
        $count = $this->connection()->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE %s', $table, $where), $params);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    /**
     * Classes of the integration events waiting in the Messenger transport (the transactional outbox).
     *
     * @return list<string>
     */
    protected function storedEventClasses(): array
    {
        $bodies = $this->connection()->fetchFirstColumn("SELECT body FROM messenger.messenger_messages WHERE queue_name = 'default' ORDER BY id");

        return array_map(static function (mixed $body): string {
            // The body is a serialised Messenger envelope (with addslashes applied); the event is the versioned class ("...V1"); stamps have other names
            $body = stripslashes(is_string($body) ? $body : '');
            preg_match('/O:\d+:"(App[^"]*V\d+)"/', $body, $matches);

            return $matches[1] ?? 'unknown';
        }, $bodies);
    }

    protected function assertEventStored(string $class): void
    {
        self::assertContains($class, $this->storedEventClasses(), "$class was not stored in the outbox");
    }

    protected function assertNoEventStored(string $class): void
    {
        self::assertNotContains($class, $this->storedEventClasses(), "$class must not be stored");
    }

    // ---- mail (Mailpit) ----

    /**
     * @return array<string, mixed>
     */
    private function mailpit(string $method, string $path): array
    {
        $http = static::getContainer()->get(HttpClientInterface::class);

        /** @var array<string, mixed> $data */
        $data = $http->request($method, self::env('MAILPIT_URL').$path)->toArray();

        return $data;
    }

    /**
     * @return list<array{subject: string, text: string, html: string}>
     */
    protected function mailsTo(string $email): array
    {
        $found = $this->mailpit('GET', '/api/v1/search?query='.rawurlencode('to:"'.$email.'"'));
        $summaries = $found['messages'] ?? [];
        self::assertIsArray($summaries);

        $mails = [];
        foreach ($summaries as $summary) {
            self::assertIsArray($summary);
            self::assertIsString($summary['ID'] ?? null);
            $message = $this->mailpit('GET', '/api/v1/message/'.$summary['ID']);
            $mails[] = [
                'subject' => is_string($message['Subject'] ?? null) ? $message['Subject'] : '',
                'text' => is_string($message['Text'] ?? null) ? $message['Text'] : '',
                'html' => is_string($message['HTML'] ?? null) ? $message['HTML'] : '',
            ];
        }

        return $mails;
    }

    /**
     * The token in the link of the newest mail to the address.
     */
    protected function tokenFromMail(string $email, string $subjectContains): string
    {
        $mails = array_values(array_filter($this->mailsTo($email), static fn (array $mail): bool => str_contains($mail['subject'], $subjectContains)));
        self::assertNotEmpty($mails, "No mail with \"$subjectContains\" reached $email");
        self::assertSame(1, preg_match('/token=([A-Za-z0-9_\-%]+)/', $mails[0]['text'], $matches));

        return rawurldecode($matches[1]);
    }
}
