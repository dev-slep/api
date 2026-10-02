<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Contract\Event\TwoFactorEnabledV1;
use App\Authentication\Contract\Event\TwoFactorFailedV1;
use App\Authentication\Contract\Event\TwoFactorVerifiedV1;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Infrastructure\Http\Controller\ConfirmTwoFactorEnrolmentController;
use App\Authentication\Infrastructure\Http\Controller\StartTwoFactorEnrolmentController;
use App\Authentication\Infrastructure\Http\Controller\VerifyTwoFactorController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\Authentication\TotpCodes;
use App\Tests\Support\Fake\FrozenClock;

use function assert;
use function count;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VerifyTwoFactorController::class)]
#[CoversClass(StartTwoFactorEnrolmentController::class)]
#[CoversClass(ConfirmTwoFactorEnrolmentController::class)]
#[CoversEndpoint('POST', '/api/v1/admin/auth/2fa/verify')]
#[CoversEndpoint('POST', '/api/v1/admin/auth/2fa/enrol')]
#[CoversEndpoint('POST', '/api/v1/admin/auth/2fa/enrol/confirm')]
final class TwoFactorEndpointsTest extends AuthenticationIntegrationTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = $this->freezeClock('2026-01-01T12:00:10+00:00');
    }

    private function code(string $secret, int $secondsFromNow = 0): string
    {
        assert('' !== $secret);

        return TotpCodes::at($secret, $this->clock->now()->getTimestamp() + $secondsFromNow);
    }

    /**
     * Logs in with the password and returns the pending token.
     *
     * @param array{email: string} $admin
     */
    private function pendingToken(array $admin): string
    {
        $response = $this->call('POST', '/api/v1/auth/login', ['email' => $admin['email'], 'password' => self::PASSWORD]);
        self::assertTrue($this->jsonBool('twoFactorRequired', $response));

        return $this->jsonString('accessToken', $response);
    }

    /**
     * An admin whose enrolment is confirmed; the clock has moved on so the confirming code is no longer the newest.
     *
     * @return array{email: string, secret: string, recoveryCodes: list<string>, id: string}
     */
    private function admin(): array
    {
        $admin = $this->createAdminWithSecondFactor();
        $this->clock->advance('+30 seconds');

        return $admin;
    }

    // ---- verify ----

    public function testAValidCodeTurnsThePendingTokenIntoAnAdminSession(): void
    {
        $admin = $this->admin();
        $pending = $this->pendingToken($admin);

        $response = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $this->code($admin['secret'])], $this->bearer($pending));

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/admin/auth/2fa/verify');
        $claims = $this->claims($this->jsonString('accessToken'));
        self::assertSame(['ROLE_ADMIN'], $claims['roles']);
        self::assertSame('pwd+otp', $claims['amr']);
        self::assertSame(1, $this->countRows('authentication.refresh_token', 'account_id = :id', ['id' => $admin['id']]));
        $this->assertEventStored(TwoFactorVerifiedV1::class);
        self::assertSame(200, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $this->jsonString('refreshToken', $response)])->getStatusCode());
    }

    public function testACodeFromTheNeighbouringStepsIsAcceptedButNotFromFurtherAway(): void
    {
        $admin = $this->admin();
        $pending = $this->pendingToken($admin);

        $farAway = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $this->code($admin['secret'], 90)], $this->bearer($pending));
        $neighbour = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $this->code($admin['secret'], 30)], $this->bearer($pending));

        self::assertSame(401, $farAway->getStatusCode());
        self::assertSame(200, $neighbour->getStatusCode());
    }

    public function testAWrongCodeFailsAndLeavesAnEventButNoSession(): void
    {
        $admin = $this->admin();
        $pending = $this->pendingToken($admin);

        $response = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => '000000'], $this->bearer($pending));

        self::assertSame(401, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/admin/auth/2fa/verify');
        self::assertSame('https://slep.example/problems/two-factor-invalid', $this->json()['type']);
        $this->assertEventStored(TwoFactorFailedV1::class);
        self::assertSame(0, $this->countRows('authentication.refresh_token'));
    }

    public function testACodeCannotBeUsedTwice(): void
    {
        $admin = $this->admin();
        $code = $this->code($admin['secret']);
        $first = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $code], $this->bearer($this->pendingToken($admin)));
        self::assertSame(200, $first->getStatusCode());

        $replay = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $code], $this->bearer($this->pendingToken($admin)));

        self::assertSame(401, $replay->getStatusCode());
        self::assertSame(1, $this->countRows('authentication.refresh_token'));
    }

    public function testARecoveryCodeWorksOnce(): void
    {
        $admin = $this->admin();
        $code = $admin['recoveryCodes'][3];

        $first = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $code], $this->bearer($this->pendingToken($admin)));
        $second = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $code], $this->bearer($this->pendingToken($admin)));

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(401, $second->getStatusCode());
        self::assertSame(9, $this->recoveryCodesLeft($admin['id']));
    }

    private function recoveryCodesLeft(string $accountId): int
    {
        $json = $this->connection()->fetchOne('SELECT recovery_code_hashes FROM authentication.two_factor_secret WHERE account_id = :id', ['id' => $accountId]);
        self::assertIsString($json);
        $hashes = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($hashes);

        return count($hashes);
    }

    public function testTheSecondFactorIsRateLimitedPerAccount(): void
    {
        $admin = $this->admin();
        $pending = $this->pendingToken($admin);

        // The test limit is 3 attempts per account per minute
        for ($i = 0; $i < 3; ++$i) {
            self::assertSame(401, $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => '00000'.$i], $this->bearer($pending))->getStatusCode());
        }
        $limited = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $this->code($admin['secret'])], $this->bearer($pending));

        self::assertSame(429, $limited->getStatusCode(), 'even the right code is refused while the limit lasts');
        self::assertMatchesOpenApiSchema($limited, 'POST', '/api/v1/admin/auth/2fa/verify');
    }

    public function testAnEmptyOrOversizedCodeFailsValidation(): void
    {
        $pending = $this->pendingToken($this->admin());

        foreach ([[], ['code' => ''], ['code' => str_repeat('1', 33)]] as $body) {
            $response = $this->call('POST', '/api/v1/admin/auth/2fa/verify', $body, $this->bearer($pending));
            self::assertSame(422, $response->getStatusCode());
            self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/admin/auth/2fa/verify');
        }
    }

    public function testAPendingTokenExpiresAfterFiveMinutes(): void
    {
        $admin = $this->admin();
        $pending = $this->pendingToken($admin);

        $this->clock->advance('+5 minutes');
        $response = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => $this->code($admin['secret'])], $this->bearer($pending));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/token-expired', $this->json()['type']);
    }

    public function testAnAdminWithoutAnEnrolmentCannotVerify(): void
    {
        $email = $this->uniqueEmail('admin');
        $account = $this->createAccount($email, AccountRole::Admin);
        $pending = $this->pendingToken(['email' => $email]);

        $response = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => '123456'], $this->bearer($pending));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/two-factor-not-enrolled', $this->json()['type']);
    }

    public function testNoTokenAnInvalidTokenOrAFullUserTokenAreRefused(): void
    {
        self::assertSame(401, $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => '123456'])->getStatusCode());
        self::assertSame(401, $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => '123456'], $this->bearer('garbage'))->getStatusCode());

        $driver = $this->createAccount();
        $tokens = $this->login($driver->email()->toString());
        $response = $this->call('POST', '/api/v1/admin/auth/2fa/verify', ['code' => '123456'], $this->bearer($tokens['accessToken']));
        self::assertSame(403, $response->getStatusCode(), 'a driver is not an admin');
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/admin/auth/2fa/verify');
        self::assertSame('https://slep.example/problems/admin-only', $this->json()['type']);
    }

    // ---- enrol ----

    public function testAnAdminWhoLostTheUnconfirmedSecretCanStartAgain(): void
    {
        $email = $this->uniqueEmail('admin');
        $created = $this->commandBus()->dispatch(new \App\Authentication\Application\Command\CreateAdmin($email, self::PASSWORD));
        assert($created instanceof \App\Authentication\Application\Command\Result\CreatedAdmin);
        $pending = $this->pendingToken(['email' => $email]);

        $response = $this->call('POST', '/api/v1/admin/auth/2fa/enrol', headers: $this->bearer($pending));

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/admin/auth/2fa/enrol');
        $secret = $this->jsonString('secret');
        self::assertNotSame($created->enrolment->secret, $secret);
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        self::assertStringStartsWith('otpauth://totp/Slep', $this->jsonString('provisioningUri'));
        self::assertSame(1, $this->countRows('authentication.two_factor_secret', 'account_id = :id', ['id' => $created->accountId]), 'the unconfirmed secret was replaced');
        $encrypted = $this->connection()->fetchOne('SELECT encrypted_secret FROM authentication.two_factor_secret WHERE account_id = :id', ['id' => $created->accountId]);
        self::assertIsString($encrypted);
        self::assertStringNotContainsString($secret, $encrypted, 'the secret is stored encrypted');
    }

    public function testAConfirmedEnrolmentCannotBeRestarted(): void
    {
        $admin = $this->admin();

        $response = $this->call('POST', '/api/v1/admin/auth/2fa/enrol', headers: $this->bearer($this->pendingToken($admin)));

        self::assertSame(409, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/admin/auth/2fa/enrol');
        self::assertSame('https://slep.example/problems/two-factor-already-enrolled', $this->json()['type']);
    }

    public function testEnrolmentNeedsATokenAndAnAdmin(): void
    {
        $anonymous = $this->call('POST', '/api/v1/admin/auth/2fa/enrol');
        self::assertSame(401, $anonymous->getStatusCode());
        self::assertMatchesOpenApiSchema($anonymous, 'POST', '/api/v1/admin/auth/2fa/enrol');

        $driver = $this->createAccount();
        $forbidden = $this->call('POST', '/api/v1/admin/auth/2fa/enrol', headers: $this->bearer($this->login($driver->email()->toString())['accessToken']));
        self::assertSame(403, $forbidden->getStatusCode());
        self::assertMatchesOpenApiSchema($forbidden, 'POST', '/api/v1/admin/auth/2fa/enrol');
    }

    // ---- confirm ----

    public function testConfirmingWithAFirstCodeReturnsTheRecoveryCodes(): void
    {
        $email = $this->uniqueEmail('admin');
        $created = $this->commandBus()->dispatch(new \App\Authentication\Application\Command\CreateAdmin($email, self::PASSWORD));
        assert($created instanceof \App\Authentication\Application\Command\Result\CreatedAdmin);
        $pending = $this->pendingToken(['email' => $email]);

        $response = $this->call('POST', '/api/v1/admin/auth/2fa/enrol/confirm', ['code' => $this->code($created->enrolment->secret)], $this->bearer($pending));

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/admin/auth/2fa/enrol/confirm');
        $codes = $this->jsonStrings('recoveryCodes');
        self::assertCount(10, $codes);
        self::assertSame(10, $this->recoveryCodesLeft($created->accountId));
        self::assertNotNull($this->connection()->fetchOne('SELECT confirmed_at FROM authentication.two_factor_secret WHERE account_id = :id', ['id' => $created->accountId]));
        $stored = $this->connection()->fetchOne('SELECT recovery_code_hashes FROM authentication.two_factor_secret WHERE account_id = :id', ['id' => $created->accountId]);
        self::assertIsString($stored);
        self::assertStringNotContainsString($codes[0], $stored, 'only hashes of the recovery codes are stored');
        self::assertStringContainsString(hash('sha256', $codes[0]), $stored);
        $this->assertEventStored(TwoFactorEnabledV1::class);
    }

    public function testConfirmingWithAWrongCodeLeavesTheEnrolmentOpen(): void
    {
        $email = $this->uniqueEmail('admin');
        $created = $this->commandBus()->dispatch(new \App\Authentication\Application\Command\CreateAdmin($email, self::PASSWORD));
        assert($created instanceof \App\Authentication\Application\Command\Result\CreatedAdmin);
        $pending = $this->pendingToken(['email' => $email]);

        $response = $this->call('POST', '/api/v1/admin/auth/2fa/enrol/confirm', ['code' => '000000'], $this->bearer($pending));

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($this->connection()->fetchOne('SELECT confirmed_at FROM authentication.two_factor_secret WHERE account_id = :id', ['id' => $created->accountId]));
        $this->assertNoEventStored(TwoFactorEnabledV1::class);
    }

    public function testConfirmingTwiceOrWithoutAnEnrolmentIsAConflict(): void
    {
        $admin = $this->admin();
        $twice = $this->call('POST', '/api/v1/admin/auth/2fa/enrol/confirm', ['code' => $this->code($admin['secret'])], $this->bearer($this->pendingToken($admin)));
        self::assertSame(409, $twice->getStatusCode());
        self::assertMatchesOpenApiSchema($twice, 'POST', '/api/v1/admin/auth/2fa/enrol/confirm');

        $email = $this->uniqueEmail('admin');
        $this->createAccount($email, AccountRole::Admin);
        $none = $this->call('POST', '/api/v1/admin/auth/2fa/enrol/confirm', ['code' => '123456'], $this->bearer($this->pendingToken(['email' => $email])));
        self::assertSame(409, $none->getStatusCode());
    }

    public function testConfirmingNeedsACodeAndAToken(): void
    {
        self::assertSame(401, $this->call('POST', '/api/v1/admin/auth/2fa/enrol/confirm', ['code' => '123456'])->getStatusCode());
        $response = $this->call('POST', '/api/v1/admin/auth/2fa/enrol/confirm', ['code' => ''], $this->bearer($this->pendingToken($this->admin())));
        self::assertSame(422, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/admin/auth/2fa/enrol/confirm');
    }
}
