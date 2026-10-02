<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Application\Port\AuthEmailSender;
use App\Authentication\Infrastructure\Http\Controller\ResendEmailVerificationController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\Authentication\FakeAuthEmailSender;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ResendEmailVerificationController::class)]
#[CoversEndpoint('POST', '/api/v1/auth/email/resend')]
final class ResendEmailVerificationEndpointTest extends AuthenticationIntegrationTestCase
{
    public function testAnUnverifiedAccountGetsANewLinkAndTheOldOneStopsWorking(): void
    {
        $account = $this->createAccount(verified: false);
        $email = $account->email()->toString();
        // Issue a first link through the application so that there is something to replace
        self::assertSame(202, $this->call('POST', '/api/v1/auth/email/resend', ['email' => $email])->getStatusCode());
        $first = $this->tokenFromMail($email, 'Confirm');

        $response = $this->call('POST', '/api/v1/auth/email/resend', ['email' => strtoupper($email)]);

        self::assertSame(202, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/email/resend');
        self::assertSame('', (string) $response->getContent());
        self::assertCount(2, $this->mailsTo($email));
        self::assertSame(422, $this->call('POST', '/api/v1/auth/email/verify', ['token' => $first])->getStatusCode(), 'the replaced link is dead');
        self::assertSame(1, $this->countRows('authentication.one_time_token', 'invalidated_at IS NOT NULL'), 'the first link was invalidated, the second is live');
    }

    public function testTheAnswerIsTheSameForUnknownVerifiedAndMalformedEmails(): void
    {
        $verified = $this->createAccount();

        foreach ([$this->uniqueEmail(), $verified->email()->toString(), 'not an email'] as $email) {
            $response = $this->call('POST', '/api/v1/auth/email/resend', ['email' => $email]);
            self::assertSame(202, $response->getStatusCode(), $email);
            self::assertSame('', (string) $response->getContent());
        }
        self::assertSame([], $this->mailsTo($verified->email()->toString()));
    }

    public function testAMailFailureIsNotReportedToTheCaller(): void
    {
        $account = $this->createAccount(verified: false);
        $mailer = new FakeAuthEmailSender();
        $mailer->failing = true;
        static::getContainer()->set(AuthEmailSender::class, $mailer);

        self::assertSame(202, $this->call('POST', '/api/v1/auth/email/resend', ['email' => $account->email()->toString()])->getStatusCode());
    }

    public function testInvalidBodiesFailValidation(): void
    {
        foreach ([[], ['email' => ''], ['email' => str_repeat('a', 255)]] as $body) {
            $response = $this->call('POST', '/api/v1/auth/email/resend', $body);
            self::assertSame(422, $response->getStatusCode());
            self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/email/resend');
        }
    }

    public function testResendingIsRateLimitedPerEmail(): void
    {
        $email = $this->uniqueEmail();

        // The test limit is 2 per email per hour
        self::assertSame(202, $this->call('POST', '/api/v1/auth/email/resend', ['email' => $email])->getStatusCode());
        self::assertSame(202, $this->call('POST', '/api/v1/auth/email/resend', ['email' => $email])->getStatusCode());
        $limited = $this->call('POST', '/api/v1/auth/email/resend', ['email' => $email]);

        self::assertSame(429, $limited->getStatusCode());
        self::assertMatchesOpenApiSchema($limited, 'POST', '/api/v1/auth/email/resend');
    }
}
