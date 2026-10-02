<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Contract\Event\PasswordChangedV1;
use App\Authentication\Infrastructure\Http\Controller\ResetPasswordController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ResetPasswordController::class)]
#[CoversEndpoint('POST', '/api/v1/auth/password/reset')]
final class ResetPasswordEndpointTest extends AuthenticationIntegrationTestCase
{
    private const string NEW_PASSWORD = 'a completely new password';

    private function requestReset(string $email): string
    {
        self::assertSame(202, $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $email])->getStatusCode());

        return $this->tokenFromMail($email, 'Reset');
    }

    public function testResettingChangesThePasswordAndEndsEverySession(): void
    {
        $account = $this->createAccount();
        $email = $account->email()->toString();
        $tokens = $this->login($email);
        $token = $this->requestReset($email);

        $response = $this->call('POST', '/api/v1/auth/password/reset', ['token' => $token, 'newPassword' => self::NEW_PASSWORD]);

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/password/reset');
        self::assertSame(401, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD])->getStatusCode());
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => self::NEW_PASSWORD])->getStatusCode());
        self::assertSame(401, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']])->getStatusCode());
        $this->assertEventStored(PasswordChangedV1::class);
        self::assertNotNull($this->accountColumn($email, 'password_changed_at'));
    }

    public function testTheLinkWorksOnce(): void
    {
        $email = $this->createAccount()->email()->toString();
        $token = $this->requestReset($email);
        $this->call('POST', '/api/v1/auth/password/reset', ['token' => $token, 'newPassword' => self::NEW_PASSWORD]);

        $second = $this->call('POST', '/api/v1/auth/password/reset', ['token' => $token, 'newPassword' => 'yet another password']);

        self::assertSame(422, $second->getStatusCode());
        self::assertMatchesOpenApiSchema($second, 'POST', '/api/v1/auth/password/reset');
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => self::NEW_PASSWORD])->getStatusCode(), 'the second attempt changed nothing');
    }

    public function testAWeakPasswordIsRefusedAndTheLinkStaysValid(): void
    {
        $email = $this->createAccount()->email()->toString();
        $token = $this->requestReset($email);

        $weak = $this->call('POST', '/api/v1/auth/password/reset', ['token' => $token, 'newPassword' => 'short']);

        self::assertSame(422, $weak->getStatusCode());
        self::assertSame('https://slep.example/problems/weak-password', $this->json()['type']);
        self::assertSame(204, $this->call('POST', '/api/v1/auth/password/reset', ['token' => $token, 'newPassword' => self::NEW_PASSWORD])->getStatusCode());
    }

    public function testTheLinkIsDeadExactlyAfterAnHour(): void
    {
        $clock = $this->freezeClock();
        $email = $this->createAccount()->email()->toString();
        $token = $this->requestReset($email);

        $clock->advance('+1 hour -1 second');
        self::assertSame(422, $this->call('POST', '/api/v1/auth/password/reset', ['token' => $token, 'newPassword' => 'short'])->getStatusCode(), 'valid link, rejected password');

        $clock->advance('+1 second');
        $expired = $this->call('POST', '/api/v1/auth/password/reset', ['token' => $token, 'newPassword' => self::NEW_PASSWORD]);

        self::assertSame(422, $expired->getStatusCode());
        self::assertSame('https://slep.example/problems/verification-token-invalid', $this->json()['type']);
        self::assertSame(401, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => self::NEW_PASSWORD])->getStatusCode());
    }

    public function testResettingProvesTheMailboxSoItVerifiesTheEmail(): void
    {
        $account = $this->createAccount(verified: false);
        $email = $account->email()->toString();
        $token = $this->requestReset($email);

        $this->call('POST', '/api/v1/auth/password/reset', ['token' => $token, 'newPassword' => self::NEW_PASSWORD]);

        self::assertNotNull($this->accountColumn($email, 'email_verified_at'));
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => self::NEW_PASSWORD])->getStatusCode());
    }

    public function testBoundariesOfTheNewPassword(): void
    {
        $email = $this->createAccount()->email()->toString();

        $tooLong = $this->call('POST', '/api/v1/auth/password/reset', ['token' => $this->requestReset($email), 'newPassword' => str_repeat('p', 129)]);
        self::assertSame(422, $tooLong->getStatusCode());
        self::assertSame('https://slep.example/problems/validation-failed', $this->json()['type']);

        self::assertSame(204, $this->call('POST', '/api/v1/auth/password/reset', ['token' => $this->tokenFromMail($email, 'Reset'), 'newPassword' => str_repeat('p', 128)])->getStatusCode());
    }

    public function testUnknownAndEmptyInputsAreRefused(): void
    {
        self::assertSame(422, $this->call('POST', '/api/v1/auth/password/reset', ['token' => 'never-issued-0123456789', 'newPassword' => self::NEW_PASSWORD])->getStatusCode());
        foreach ([[], ['token' => '', 'newPassword' => ''], ['token' => 'x'], ['newPassword' => 'x'], ['token' => str_repeat('a', 257), 'newPassword' => 'x']] as $body) {
            $response = $this->call('POST', '/api/v1/auth/password/reset', $body);
            self::assertSame(422, $response->getStatusCode());
            self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/password/reset');
        }
    }

    public function testAVerificationTokenCannotResetAPassword(): void
    {
        $email = $this->uniqueEmail();
        $this->call('POST', '/api/v1/auth/register', ['email' => $email, 'password' => self::PASSWORD, 'role' => 'DRIVER', 'locale' => 'en']);

        $response = $this->call('POST', '/api/v1/auth/password/reset', ['token' => $this->tokenFromMail($email, 'Confirm'), 'newPassword' => self::NEW_PASSWORD]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(401, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => self::NEW_PASSWORD])->getStatusCode());
    }
}
