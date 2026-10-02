<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Application\Port\AuthEmailSender;
use App\Authentication\Contract\Event\UserRegisteredV1;
use App\Authentication\Infrastructure\Http\Controller\RegisterController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\Authentication\FakeAuthEmailSender;

use function is_string;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(RegisterController::class)]
#[CoversEndpoint('POST', '/api/v1/auth/register')]
final class RegisterEndpointTest extends AuthenticationIntegrationTestCase
{
    /**
     * @param array<string, mixed> $body
     */
    private function emailOf(array $body): string
    {
        self::assertIsString($body['email'] ?? null);

        return $body['email'];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function body(array $override = []): array
    {
        return $override + ['email' => $this->uniqueEmail(), 'password' => self::PASSWORD, 'role' => 'DRIVER', 'phone' => '+381 64 123 4567', 'locale' => 'en'];
    }

    public function testRegisteringCreatesTheAccountSendsTheMailAndStoresTheEvent(): void
    {
        $body = $this->body(['email' => 'Ana.'.bin2hex(random_bytes(4)).'@Example.com', 'role' => 'TOWER']);

        $response = $this->call('POST', '/api/v1/auth/register', $body);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/register');
        self::assertSame(['email' => mb_strtolower($this->emailOf($body)), 'emailVerificationRequired' => true], $this->json());

        $row = $this->accountRow($this->emailOf($body));
        self::assertNotNull($row);
        self::assertSame('TOWER', $row['role']);
        self::assertSame('+381641234567', $row['phone']);
        self::assertSame('en', $row['locale']);
        self::assertNull($row['email_verified_at']);
        self::assertSame('ACTIVE', $row['status']);
        self::assertIsString($row['password_hash']);
        self::assertStringStartsWith('$argon2id$', $row['password_hash']);
        self::assertSame(1, $this->countRows('authentication.one_time_token', "purpose = 'EMAIL_VERIFICATION' AND account_id = :id", ['id' => $row['id']]));
        $this->assertEventStored(UserRegisteredV1::class);

        $mails = $this->mailsTo($this->emailOf($body));
        self::assertCount(1, $mails);
        self::assertSame('Confirm your email address', $mails[0]['subject']);
        self::assertStringContainsString('/verify-email?token=', $mails[0]['text']);
    }

    public function testTheMailIsInTheLanguageOfTheAccount(): void
    {
        $body = $this->body(['locale' => 'sr_Latn']);

        $this->call('POST', '/api/v1/auth/register', $body);

        self::assertSame('Potvrdite svoju e-poštu', $this->mailsTo($this->emailOf($body))[0]['subject']);
    }

    public function testNoPasswordOrTokenIsStoredInTheClear(): void
    {
        $body = $this->body();
        $this->call('POST', '/api/v1/auth/register', $body);

        $token = $this->tokenFromMail($this->emailOf($body), 'Confirm');
        $stored = $this->connection()->fetchAllAssociative('SELECT * FROM authentication.one_time_token');
        $rows = json_encode([$this->accountRow($this->emailOf($body)), $stored], JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString(self::PASSWORD, $rows);
        self::assertStringNotContainsString($token, $rows);
        self::assertStringContainsString(hash('sha256', $token), $rows);
        self::assertStringNotContainsString(self::PASSWORD, implode("\n", array_map(static fn (mixed $body): string => is_string($body) ? $body : '', $this->connection()->fetchFirstColumn('SELECT body FROM messenger.messenger_messages'))));
    }

    /**
     * @return iterable<int|string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'email missing' => [['email' => null], ['email']];
        yield 'email empty' => [['email' => ''], ['email']];
        yield 'email malformed' => [['email' => 'not an email'], ['email']];
        yield 'email too long' => [['email' => str_repeat('a', 250).'@example.com'], ['email']];
        yield 'password missing' => [['password' => null], ['password']];
        yield 'password empty' => [['password' => ''], ['password']];
        yield 'password one too long' => [['password' => str_repeat('a', 129)], ['password']];
        yield 'role missing' => [['role' => null], ['role']];
        yield 'role admin' => [['role' => 'ADMIN'], ['role']];
        yield 'role unknown' => [['role' => 'PILOT'], ['role']];
        yield 'role lowercase' => [['role' => 'driver'], ['role']];
        yield 'phone too long' => [['phone' => str_repeat('1', 33)], ['phone']];
        yield 'locale malformed' => [['locale' => 'EN'], ['locale']];
        yield 'everything wrong' => [['email' => 'x', 'password' => '', 'role' => 'X'], ['email', 'password', 'role']];
    }

    /**
     * @param array<string, mixed> $override
     * @param list<string>         $fields
     */
    #[DataProvider('invalidBodies')]
    public function testInvalidBodiesAreRefusedWithTheFieldsAtFault(array $override, array $fields): void
    {
        $body = array_filter($override + $this->body(), static fn (mixed $value): bool => null !== $value);

        $response = $this->call('POST', '/api/v1/auth/register', $body);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/register');
        self::assertSame($fields, $this->errorFields());
        self::assertSame(0, $this->countRows('authentication.user_account', 'email = :email', ['email' => mb_strtolower(is_string($body['email'] ?? null) ? $body['email'] : '')]));
        self::assertSame([], $this->storedEventClasses());
    }

    public function testBoundaryValuesAreAccepted(): void
    {
        $email = $this->uniqueEmail();
        $response = $this->call('POST', '/api/v1/auth/register', ['email' => $email, 'password' => str_repeat('p', 128), 'role' => 'DRIVER', 'phone' => '1234567']);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheTwoMaximumLengthsAreAcceptedAndOneMoreIsNot(): void
    {
        self::assertSame(422, $this->call('POST', '/api/v1/auth/register', $this->body(['password' => str_repeat('p', 129)]))->getStatusCode());
        self::assertSame(201, $this->call('POST', '/api/v1/auth/register', $this->body(['password' => str_repeat('p', 128)]))->getStatusCode());
    }

    public function testAWeakPasswordIsRefusedWithItsOwnProblemType(): void
    {
        $response = $this->call('POST', '/api/v1/auth/register', $this->body(['password' => 'short']));

        self::assertSame(422, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/register');
        self::assertSame('https://slep.example/problems/weak-password', $this->json()['type']);
    }

    public function testRegisteringAnExistingEmailIsAConflictWhateverTheCase(): void
    {
        $body = $this->body();
        self::assertSame(201, $this->call('POST', '/api/v1/auth/register', $body)->getStatusCode());

        $response = $this->call('POST', '/api/v1/auth/register', ['email' => mb_strtoupper($this->emailOf($body))] + $body);

        self::assertSame(409, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/register');
        self::assertSame('https://slep.example/problems/email-already-registered', $this->json()['type']);
        self::assertCount(1, $this->mailsTo($this->emailOf($body)));
        self::assertSame(1, $this->countRows('authentication.user_account', 'email = :email', ['email' => $this->emailOf($body)]));
    }

    public function testAMailFailureLeavesNoAccountBehind(): void
    {
        $mailer = new FakeAuthEmailSender();
        $mailer->failing = true;
        static::getContainer()->set(AuthEmailSender::class, $mailer);
        $body = $this->body();

        $response = $this->call('POST', '/api/v1/auth/register', $body);

        self::assertSame(500, $response->getStatusCode());
        self::assertNull($this->accountRow($this->emailOf($body)), 'the registration was rolled back');
        self::assertSame([], $this->storedEventClasses());
    }

    public function testASendingKeyReplaysTheFirstResponseWithoutRegisteringTwice(): void
    {
        $body = $this->body();
        $key = ['Idempotency-Key' => 'register-'.bin2hex(random_bytes(8))];

        $first = $this->call('POST', '/api/v1/auth/register', $body, $key);
        $second = $this->call('POST', '/api/v1/auth/register', $body, $key);

        self::assertSame(201, $first->getStatusCode());
        self::assertSame(201, $second->getStatusCode(), 'a retry gets the first answer, not a 409');
        self::assertSame('true', $second->headers->get('Idempotency-Replayed'));
        self::assertSame($first->getContent(), $second->getContent());
        self::assertCount(1, $this->mailsTo($this->emailOf($body)));
        self::assertSame(1, $this->countRows('authentication.user_account', 'email = :email', ['email' => $this->emailOf($body)]));
    }

    public function testTheSameKeyWithAnotherBodyIsRefused(): void
    {
        $key = ['Idempotency-Key' => 'register-'.bin2hex(random_bytes(8))];
        $this->call('POST', '/api/v1/auth/register', $this->body(), $key);

        $response = $this->call('POST', '/api/v1/auth/register', $this->body(), $key);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/idempotency-key-reused', $this->json()['type']);
    }

    public function testAFailedRequestDoesNotUseUpItsKey(): void
    {
        $key = ['Idempotency-Key' => 'register-'.bin2hex(random_bytes(8))];
        $body = $this->body();
        self::assertSame(422, $this->call('POST', '/api/v1/auth/register', ['role' => 'PILOT'] + $body, $key)->getStatusCode());

        self::assertSame(201, $this->call('POST', '/api/v1/auth/register', $body, $key)->getStatusCode(), 'the failed attempt did not use up the key');
    }

    public function testRegistrationIsRateLimitedPerAddress(): void
    {
        $limit = 100;
        $last = null;
        for ($i = 0; $i <= $limit; ++$i) {
            $last = $this->call('POST', '/api/v1/auth/register', ['email' => 'bad', 'password' => '', 'role' => 'X']);
            if (429 === $last->getStatusCode()) {
                break;
            }
        }

        self::assertNotNull($last);
        self::assertSame(429, $last->getStatusCode());
        self::assertMatchesOpenApiSchema($last, 'POST', '/api/v1/auth/register');
        self::assertGreaterThanOrEqual(1, (int) $last->headers->get('Retry-After'));
    }

    public function testRequestsWithoutAJsonContentTypeAreRefused(): void
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'text/plain'], content: 'email=a');

        self::assertSame(415, $this->client->getResponse()->getStatusCode());
    }

    public function testMalformedJsonIsRefused(): void
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: '{"email": ');

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame('https://slep.example/problems/malformed-request', $this->json()['type']);
    }

    public function testAnAuthenticatedUserMayRegisterToo(): void
    {
        $account = $this->createAccount();
        $tokens = $this->login($account->email()->toString());

        self::assertSame(201, $this->call('POST', '/api/v1/auth/register', $this->body(), $this->bearer($tokens['accessToken']))->getStatusCode());
    }
}
