<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Contract\Event\PasswordResetRequestedV1;
use App\Authentication\Infrastructure\Http\Controller\ForgotPasswordController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ForgotPasswordController::class)]
#[CoversEndpoint('POST', '/api/v1/auth/password/forgot')]
final class ForgotPasswordEndpointTest extends AuthenticationIntegrationTestCase
{
    public function testAnExistingAccountGetsAResetLinkAndTheRequestIsRecorded(): void
    {
        $account = $this->createAccount();
        $email = $account->email()->toString();

        $response = $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $email]);

        self::assertSame(202, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/password/forgot');
        self::assertSame('', (string) $response->getContent());
        $mails = $this->mailsTo($email);
        self::assertCount(1, $mails);
        self::assertSame('Reset your password', $mails[0]['subject']);
        self::assertStringContainsString('/reset-password?token=', $mails[0]['text']);
        self::assertSame(1, $this->countRows('authentication.one_time_token', "purpose = 'PASSWORD_RESET' AND account_id = :id", ['id' => $account->id()->toString()]));
        $this->assertEventStored(PasswordResetRequestedV1::class);
    }

    public function testTheLinkExpiresAfterAnHour(): void
    {
        $this->freezeClock();
        $account = $this->createAccount();
        $email = $account->email()->toString();
        $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $email]);
        $stored = $this->connection()->fetchAssociative('SELECT issued_at, expires_at FROM authentication.one_time_token WHERE account_id = :id', ['id' => $account->id()->toString()]);

        self::assertNotFalse($stored);
        self::assertIsString($stored['issued_at']);
        self::assertIsString($stored['expires_at']);
        self::assertSame(3600, (int) strtotime($stored['expires_at']) - (int) strtotime($stored['issued_at']));
    }

    public function testNothingRevealsWhetherTheAccountExists(): void
    {
        $banned = $this->createAccount();
        $this->rawUpdate("UPDATE authentication.user_account SET status = 'BANNED' WHERE id = :id", ['id' => $banned->id()->toString()]);

        foreach ([$this->uniqueEmail(), 'not an email', $banned->email()->toString()] as $email) {
            $response = $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $email]);
            self::assertSame(202, $response->getStatusCode(), $email);
            self::assertSame('', (string) $response->getContent());
        }
        self::assertSame([], $this->mailsTo($banned->email()->toString()));
        self::assertSame(0, $this->countRows('authentication.one_time_token'));
    }

    public function testASecondRequestKillsTheFirstLink(): void
    {
        $account = $this->createAccount();
        $email = $account->email()->toString();
        $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $email]);
        $first = $this->tokenFromMail($email, 'Reset');

        $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $email]);

        self::assertSame(422, $this->call('POST', '/api/v1/auth/password/reset', ['token' => $first, 'newPassword' => 'a completely new password'])->getStatusCode());
    }

    public function testInvalidBodiesFailValidation(): void
    {
        foreach ([[], ['email' => ''], ['email' => str_repeat('a', 255)]] as $body) {
            $response = $this->call('POST', '/api/v1/auth/password/forgot', $body);
            self::assertSame(422, $response->getStatusCode());
            self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/password/forgot');
        }
    }

    public function testRequestsAreRateLimitedPerEmail(): void
    {
        $email = $this->uniqueEmail();

        // The test limit is 2 per email per hour
        $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $email]);
        $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $email]);
        $limited = $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $email]);

        self::assertSame(429, $limited->getStatusCode());
        self::assertMatchesOpenApiSchema($limited, 'POST', '/api/v1/auth/password/forgot');
    }
}
