<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Contract\Event\LoginFailedV1;
use App\Authentication\Contract\Event\UserLoggedInV1;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Infrastructure\Http\Controller\LoginController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(LoginController::class)]
#[CoversEndpoint('POST', '/api/v1/auth/login')]
final class LoginEndpointTest extends AuthenticationIntegrationTestCase
{
    public function testAVerifiedAccountLogsInAndTheSessionIsStored(): void
    {
        $account = $this->createAccount();

        $response = $this->call('POST', '/api/v1/auth/login', ['email' => strtoupper($account->email()->toString()), 'password' => self::PASSWORD]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/login');
        $body = $this->json();
        self::assertSame('Bearer', $body['tokenType']);
        self::assertSame(900, $body['expiresIn']);
        self::assertCount(3, explode('.', $this->jsonString('accessToken')));
        $refreshToken = $this->jsonString('refreshToken');

        $stored = $this->connection()->fetchAllAssociative('SELECT * FROM authentication.refresh_token WHERE account_id = :id', ['id' => $account->id()->toString()]);
        self::assertCount(1, $stored);
        self::assertSame(hash('sha256', $refreshToken), $stored[0]['hash'], 'only the hash is stored');
        self::assertNull($stored[0]['rotated_at']);
        self::assertNull($stored[0]['revoked_at']);
        $this->assertEventStored(UserLoggedInV1::class);
    }

    public function testTheAccessTokenCarriesTheAccountAndItsRole(): void
    {
        $account = $this->createAccount(role: AccountRole::Tower);

        $token = $this->login($account->email()->toString())['accessToken'];

        $claims = $this->claims($token);
        self::assertSame($account->id()->toString(), $claims['sub']);
        self::assertSame(['ROLE_TOWER'], $claims['roles']);
        self::assertSame('pwd', $claims['amr']);
        self::assertIsInt($claims['iat']);
        self::assertSame($claims['iat'] + 900, $claims['exp']);
    }

    public function testWrongCredentialsAreRefusedTheSameWayForKnownAndUnknownEmails(): void
    {
        $account = $this->createAccount();

        $wrongPassword = $this->call('POST', '/api/v1/auth/login', ['email' => $account->email()->toString(), 'password' => 'definitely wrong']);
        $wrongPasswordBody = $this->json();
        $unknown = $this->call('POST', '/api/v1/auth/login', ['email' => $this->uniqueEmail(), 'password' => 'definitely wrong']);
        $unknownBody = $this->json();

        foreach ([$wrongPassword, $unknown] as $response) {
            self::assertSame(401, $response->getStatusCode());
            self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/login');
        }
        self::assertSame($wrongPasswordBody['type'], $unknownBody['type']);
        self::assertSame($wrongPasswordBody['title'], $unknownBody['title']);
        self::assertSame($wrongPasswordBody['detail'], $unknownBody['detail']);
        self::assertSame(0, $this->countRows('authentication.refresh_token'));
        $this->assertEventStored(LoginFailedV1::class);
        self::assertCount(2, array_keys(array_filter($this->storedEventClasses(), static fn (string $class): bool => LoginFailedV1::class === $class)), 'both failures leave an event although the response is a 401');
    }

    public function testAnUnverifiedEmailCannotLogIn(): void
    {
        $account = $this->createAccount(verified: false);

        $response = $this->call('POST', '/api/v1/auth/login', ['email' => $account->email()->toString(), 'password' => self::PASSWORD]);

        self::assertSame(403, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/login');
        self::assertSame('https://slep.example/problems/email-not-verified', $this->json()['type']);
        self::assertSame(0, $this->countRows('authentication.refresh_token'));
    }

    public function testABannedAccountCannotLogIn(): void
    {
        $account = $this->createAccount();
        $this->rawUpdate("UPDATE authentication.user_account SET status = 'BANNED' WHERE id = :id", ['id' => $account->id()->toString()]);

        $response = $this->call('POST', '/api/v1/auth/login', ['email' => $account->email()->toString(), 'password' => self::PASSWORD]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/account-banned', $this->json()['type']);
    }

    public function testAnAdminGetsOnlyAPendingTokenWithoutRolesOrARefreshToken(): void
    {
        $admin = $this->createAdminWithSecondFactor();

        $response = $this->call('POST', '/api/v1/auth/login', ['email' => $admin['email'], 'password' => self::PASSWORD]);

        self::assertSame(200, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/login');
        $body = $this->json();
        self::assertTrue($body['twoFactorRequired']);
        self::assertSame(300, $body['expiresIn']);
        self::assertArrayNotHasKey('refreshToken', $body);
        $claims = $this->claims($this->jsonString('accessToken'));
        self::assertSame([], $claims['roles']);
        self::assertSame('pending_2fa', $claims['amr']);
        self::assertSame(0, $this->countRows('authentication.refresh_token'));
    }

    public function testASocialOnlyAccountCannotLogInWithAPassword(): void
    {
        $account = $this->createAccount();
        $this->rawUpdate('UPDATE authentication.user_account SET password_hash = NULL WHERE id = :id', ['id' => $account->id()->toString()]);

        self::assertSame(401, $this->call('POST', '/api/v1/auth/login', ['email' => $account->email()->toString(), 'password' => self::PASSWORD])->getStatusCode());
    }

    /**
     * @return iterable<int|string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'email missing' => [['email' => null], ['email']];
        yield 'email blank' => [['email' => ''], ['email']];
        yield 'email too long' => [['email' => str_repeat('a', 255)], ['email']];
        yield 'password missing' => [['password' => null], ['password']];
        yield 'password blank' => [['password' => ''], ['password']];
        yield 'password too long' => [['password' => str_repeat('a', 129)], ['password']];
        yield 'both missing' => [['email' => null, 'password' => null, 'unrelated' => true], ['email', 'password']];
    }

    /**
     * @param array<string, mixed> $override
     * @param list<string>         $fields
     */
    #[DataProvider('invalidBodies')]
    public function testInvalidBodiesAreRefused(array $override, array $fields): void
    {
        $body = array_filter($override + ['email' => 'a@example.com', 'password' => 'x'], static fn (mixed $value): bool => null !== $value);

        $response = $this->call('POST', '/api/v1/auth/login', $body);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/login');
        self::assertSame($fields, $this->errorFields());
    }

    public function testWrongTypesAndMalformedJsonAreRefused(): void
    {
        self::assertSame(422, $this->call('POST', '/api/v1/auth/login', ['email' => ['a'], 'password' => 5])->getStatusCode());
        $this->client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: '[');
        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'text/plain'], content: 'x');
        self::assertSame(415, $this->client->getResponse()->getStatusCode());
    }

    public function testRepeatedFailuresForOneEmailAreRateLimited(): void
    {
        $email = $this->uniqueEmail();

        // The test limit is 3 attempts per email per minute
        for ($i = 0; $i < 3; ++$i) {
            self::assertSame(401, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => 'wrong wrong wrong'])->getStatusCode());
        }
        $limited = $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => 'wrong wrong wrong']);

        self::assertSame(429, $limited->getStatusCode());
        self::assertMatchesOpenApiSchema($limited, 'POST', '/api/v1/auth/login');
        self::assertGreaterThanOrEqual(1, (int) $limited->headers->get('Retry-After'));
        self::assertSame(401, $this->call('POST', '/api/v1/auth/login', ['email' => $this->uniqueEmail(), 'password' => 'wrong wrong wrong'])->getStatusCode(), 'other emails are not affected');
    }

    public function testTheLimitAlsoStopsTheRightPasswordWhileItLasts(): void
    {
        $account = $this->createAccount();
        for ($i = 0; $i < 3; ++$i) {
            $this->call('POST', '/api/v1/auth/login', ['email' => $account->email()->toString(), 'password' => 'wrong wrong wrong']);
        }

        self::assertSame(429, $this->call('POST', '/api/v1/auth/login', ['email' => $account->email()->toString(), 'password' => self::PASSWORD])->getStatusCode());
    }

    public function testEachLoginStartsItsOwnSessionFamily(): void
    {
        $account = $this->createAccount();

        $first = $this->login($account->email()->toString());
        $second = $this->login($account->email()->toString());

        self::assertNotSame($first['refreshToken'], $second['refreshToken']);
        self::assertSame(2, $this->countRows('authentication.refresh_token', 'account_id = :id', ['id' => $account->id()->toString()]));
        self::assertEquals(2, $this->connection()->fetchOne('SELECT COUNT(DISTINCT family_id) FROM authentication.refresh_token WHERE account_id = :id', ['id' => $account->id()->toString()]));
    }
}
