<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Contract\Event\EmailVerifiedV1;
use App\Authentication\Infrastructure\Http\Controller\VerifyEmailController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VerifyEmailController::class)]
#[CoversEndpoint('POST', '/api/v1/auth/email/verify')]
final class VerifyEmailEndpointTest extends AuthenticationIntegrationTestCase
{
    private function register(): string
    {
        $email = $this->uniqueEmail();
        self::assertSame(201, $this->call('POST', '/api/v1/auth/register', ['email' => $email, 'password' => self::PASSWORD, 'role' => 'DRIVER', 'locale' => 'en'])->getStatusCode());

        return $email;
    }

    public function testFollowingTheLinkVerifiesTheEmailAndUnlocksTheLogin(): void
    {
        $email = $this->register();
        self::assertSame(403, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD])->getStatusCode());

        $response = $this->call('POST', '/api/v1/auth/email/verify', ['token' => $this->tokenFromMail($email, 'Confirm')]);

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/email/verify');
        self::assertNotNull($this->accountColumn($email, 'email_verified_at'));
        self::assertSame(1, $this->countRows('authentication.one_time_token', 'used_at IS NOT NULL'));
        $this->assertEventStored(EmailVerifiedV1::class);
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD])->getStatusCode());
    }

    public function testALinkWorksOnlyOnce(): void
    {
        $email = $this->register();
        $token = $this->tokenFromMail($email, 'Confirm');
        $this->call('POST', '/api/v1/auth/email/verify', ['token' => $token]);

        $second = $this->call('POST', '/api/v1/auth/email/verify', ['token' => $token]);

        self::assertSame(422, $second->getStatusCode());
        self::assertMatchesOpenApiSchema($second, 'POST', '/api/v1/auth/email/verify');
        self::assertSame('https://slep.example/problems/verification-token-invalid', $this->json()['type']);
    }

    public function testALinkIsDeadExactlyAfter24Hours(): void
    {
        $clock = $this->freezeClock();
        $email = $this->register();
        $token = $this->tokenFromMail($email, 'Confirm');

        $clock->advance('+24 hours');
        $expired = $this->call('POST', '/api/v1/auth/email/verify', ['token' => $token]);

        self::assertSame(422, $expired->getStatusCode());
        self::assertNull($this->accountColumn($email, 'email_verified_at'));
    }

    public function testALinkWorksUpToTheLastSecond(): void
    {
        $clock = $this->freezeClock();
        $email = $this->register();
        $token = $this->tokenFromMail($email, 'Confirm');

        $clock->advance('+24 hours -1 second');

        self::assertSame(204, $this->call('POST', '/api/v1/auth/email/verify', ['token' => $token])->getStatusCode());
    }

    public function testUnknownAndTamperedTokensAreRefusedTheSameWay(): void
    {
        $email = $this->register();
        $token = $this->tokenFromMail($email, 'Confirm');

        foreach (['unknown-token-value-0123456789', $token.'x', strrev($token)] as $bad) {
            $response = $this->call('POST', '/api/v1/auth/email/verify', ['token' => $bad]);
            self::assertSame(422, $response->getStatusCode(), $bad);
            self::assertSame('https://slep.example/problems/verification-token-invalid', $this->json()['type']);
        }
        self::assertNull($this->accountColumn($email, 'email_verified_at'));
    }

    public function testAPasswordResetTokenCannotVerifyAnEmail(): void
    {
        $account = $this->createAccount(verified: false);
        $this->call('POST', '/api/v1/auth/password/forgot', ['email' => $account->email()->toString()]);

        $response = $this->call('POST', '/api/v1/auth/email/verify', ['token' => $this->tokenFromMail($account->email()->toString(), 'Reset')]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testEmptyAndOversizedTokensFailValidation(): void
    {
        foreach ([[], ['token' => ''], ['token' => str_repeat('a', 257)]] as $body) {
            $response = $this->call('POST', '/api/v1/auth/email/verify', $body);
            self::assertSame(422, $response->getStatusCode());
            self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/email/verify');
        }
    }

    public function testGuessingIsRateLimited(): void
    {
        $limited = null;
        for ($i = 0; $i < 105 && null === $limited; ++$i) {
            $response = $this->call('POST', '/api/v1/auth/email/verify', ['token' => 'guess-'.$i.'-0123456789012345']);
            $limited = 429 === $response->getStatusCode() ? $response : null;
        }

        self::assertNotNull($limited);
        self::assertMatchesOpenApiSchema($limited, 'POST', '/api/v1/auth/email/verify');
    }
}
