<?php

declare(strict_types=1);

namespace App\Tests\Application\Authentication;

use App\Authentication\Application\Command\CreateAdmin;
use App\Authentication\Application\Command\Result\CreatedAdmin;
use App\Authentication\Contract\Event\EmailVerifiedV1;
use App\Authentication\Contract\Event\LoginFailedV1;
use App\Authentication\Contract\Event\RefreshTokenReuseDetectedV1;
use App\Authentication\Contract\Event\UserLoggedInV1;
use App\Authentication\Contract\Event\UserLoggedOutV1;
use App\Authentication\Contract\Event\UserRegisteredV1;
use App\SharedKernel\Application\CommandBus;
use App\Tests\Support\Authentication\AuthenticationApplicationTestCase;
use App\Tests\Support\Authentication\TotpCodes;

use function assert;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Whole use cases through HTTP: security, serialisation, validation, buses and the outbox, with in-memory storage.
 */
#[CoversNothing]
final class AuthenticationFlowTest extends AuthenticationApplicationTestCase
{
    private const string PASSWORD = 'correct horse battery';

    private function registerAndVerify(string $email = 'ana@example.com', string $role = 'DRIVER'): void
    {
        self::assertSame(201, $this->jsonRequest('POST', '/api/v1/auth/register', ['email' => $email, 'password' => self::PASSWORD, 'role' => $role, 'phone' => '+381641234567'])->getStatusCode());
        self::assertSame(204, $this->jsonRequest('POST', '/api/v1/auth/email/verify', ['token' => $this->mailer->lastToken('verification')])->getStatusCode());
    }

    /**
     * @return array{accessToken: string, refreshToken: string, expiresIn: int, tokenType: string}
     */
    private function login(string $email = 'ana@example.com'): array
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        /** @var array{accessToken: string, refreshToken: string, expiresIn: int, tokenType: string} $body */
        $body = $this->json();

        return $body;
    }

    public function testTheWholeLifeOfADriversSession(): void
    {
        $registered = $this->jsonRequest('POST', '/api/v1/auth/register', ['email' => 'Ana@Example.com', 'password' => self::PASSWORD, 'role' => 'DRIVER']);
        self::assertSame(201, $registered->getStatusCode());
        self::assertSame(['email' => 'ana@example.com', 'emailVerificationRequired' => true], $this->json());

        $blocked = $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => self::PASSWORD]);
        self::assertSame(403, $blocked->getStatusCode());
        self::assertSame('https://slep.example/problems/email-not-verified', $this->json()['type']);

        self::assertSame(204, $this->jsonRequest('POST', '/api/v1/auth/email/verify', ['token' => $this->mailer->lastToken('verification')])->getStatusCode());

        $tokens = $this->login();
        self::assertSame('Bearer', $tokens['tokenType']);
        self::assertSame(900, $tokens['expiresIn']);
        self::assertCount(3, explode('.', $tokens['accessToken']));

        $refreshed = $this->jsonRequest('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']]);
        self::assertSame(200, $refreshed->getStatusCode());
        $second = $this->json();
        self::assertNotSame($tokens['refreshToken'], $second['refreshToken']);

        $reuse = $this->jsonRequest('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']]);
        self::assertSame(401, $reuse->getStatusCode());
        self::assertSame('https://slep.example/problems/token-revoked', $this->json()['type']);
        self::assertSame(401, $this->jsonRequest('POST', '/api/v1/auth/refresh', ['refreshToken' => $second['refreshToken']])->getStatusCode(), 'the whole family is revoked');

        $third = $this->login();
        self::assertSame(204, $this->jsonRequest('POST', '/api/v1/auth/logout', ['refreshToken' => $third['refreshToken']])->getStatusCode());
        self::assertSame(401, $this->jsonRequest('POST', '/api/v1/auth/refresh', ['refreshToken' => $third['refreshToken']])->getStatusCode());
    }

    public function testTheAuditableEventsAreInTheOutbox(): void
    {
        $this->registerAndVerify();
        $tokens = $this->login();
        $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'wrong wrong wrong']);
        $this->jsonRequest('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']]);
        $this->jsonRequest('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']]);
        $this->jsonRequest('POST', '/api/v1/auth/logout', ['refreshToken' => $tokens['refreshToken'], 'allDevices' => true]);

        $classes = array_map(static fn (object $event): string => $event::class, $this->allPublishedEvents());

        self::assertContains(UserRegisteredV1::class, $classes);
        self::assertContains(EmailVerifiedV1::class, $classes);
        self::assertContains(UserLoggedInV1::class, $classes);
        self::assertContains(LoginFailedV1::class, $classes, 'a failed login keeps its event although the request answers 401');
        self::assertContains(RefreshTokenReuseDetectedV1::class, $classes, 'token reuse keeps its event although the request answers 401');
        self::assertContains(UserLoggedOutV1::class, $classes);
    }

    public function testAFailedLoginAnswers401WithAProblemAndNoTokens(): void
    {
        $this->registerAndVerify();

        $response = $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'wrong wrong wrong']);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $body = $this->json();
        self::assertSame('https://slep.example/problems/invalid-credentials', $body['type']);
        self::assertSame('Pogrešna e-pošta ili lozinka', $body['title']);
        self::assertArrayNotHasKey('accessToken', $body);
    }

    public function testProblemTitlesFollowTheAcceptLanguageHeader(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever1234'], ['Accept-Language' => 'en']);

        self::assertSame('Incorrect email or password', $this->json()['title']);
    }

    public function testValidationErrorsNameTheFields(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/register', ['email' => 'not an email', 'password' => '', 'role' => 'PILOT']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['email', 'password', 'role'], $this->errorFields());
    }

    public function testAWeakPasswordIsRefusedByThePolicy(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/register', ['email' => 'ana@example.com', 'password' => 'short', 'role' => 'DRIVER']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/weak-password', $this->json()['type']);
    }

    public function testRegisteringTwiceIsAConflict(): void
    {
        $this->registerAndVerify();

        $response = $this->jsonRequest('POST', '/api/v1/auth/register', ['email' => 'ANA@example.com', 'password' => self::PASSWORD, 'role' => 'TOWER']);

        self::assertSame(409, $response->getStatusCode());
    }

    public function testTheRequestLocaleDecidesTheLanguageOfTheEmail(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', ['email' => 'ana@example.com', 'password' => self::PASSWORD, 'role' => 'DRIVER', 'locale' => 'en']);
        $this->jsonRequest('POST', '/api/v1/auth/register', ['email' => 'bob@example.com', 'password' => self::PASSWORD, 'role' => 'DRIVER']);

        self::assertSame(['en', 'sr_Latn'], array_column($this->mailer->sent, 'locale'));
    }

    public function testPasswordResetEndsEverySessionAndAcceptsTheNewPassword(): void
    {
        $this->registerAndVerify();
        $tokens = $this->login();

        self::assertSame(202, $this->jsonRequest('POST', '/api/v1/auth/password/forgot', ['email' => 'ana@example.com'])->getStatusCode());
        self::assertSame(204, $this->jsonRequest('POST', '/api/v1/auth/password/reset', ['token' => $this->mailer->lastToken('reset'), 'newPassword' => 'a completely new password'])->getStatusCode());

        self::assertSame(401, $this->jsonRequest('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']])->getStatusCode());
        self::assertSame(401, $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => self::PASSWORD])->getStatusCode());
        self::assertSame(200, $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'a completely new password'])->getStatusCode());
    }

    public function testForgotPasswordAnswersTheSameForUnknownAccounts(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/password/forgot', ['email' => 'nobody@example.com']);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame([], $this->mailer->sent);
    }

    public function testResendingTheVerificationEmail(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', ['email' => 'ana@example.com', 'password' => self::PASSWORD, 'role' => 'DRIVER']);

        self::assertSame(202, $this->jsonRequest('POST', '/api/v1/auth/email/resend', ['email' => 'ana@example.com'])->getStatusCode());
        self::assertSame(202, $this->jsonRequest('POST', '/api/v1/auth/email/resend', ['email' => 'nobody@example.com'])->getStatusCode());

        self::assertSame(2, $this->mailer->count('verification'));
    }

    public function testSocialSignInThroughHttp(): void
    {
        $this->social->trust('google-id-token', \App\Authentication\Domain\Model\SocialProvider::Google, 'g-1', 'soc@example.com');

        $response = $this->jsonRequest('POST', '/api/v1/auth/social/google', ['idToken' => 'google-id-token', 'role' => 'TOWER']);

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayHasKey('refreshToken', $this->json());
        self::assertSame(401, $this->jsonRequest('POST', '/api/v1/auth/social/google', ['idToken' => 'forged'])->getStatusCode());
        self::assertSame(404, $this->jsonRequest('POST', '/api/v1/auth/social/facebook', ['idToken' => 'x'])->getStatusCode());
    }

    public function testProtectedRoutesNeedAValidToken(): void
    {
        $this->registerAndVerify();
        $tokens = $this->login();

        $anonymous = $this->jsonRequest('POST', '/api/v1/admin/auth/2fa/enrol');
        self::assertSame(401, $anonymous->getStatusCode());
        self::assertSame('Bearer', $anonymous->headers->get('WWW-Authenticate'));
        self::assertSame('https://slep.example/problems/unauthorized', $this->json()['type']);

        self::assertSame(401, $this->jsonRequest('POST', '/api/v1/admin/auth/2fa/enrol', headers: $this->bearer('garbage'))->getStatusCode());
        self::assertSame('https://slep.example/problems/token-invalid', $this->json()['type']);

        self::assertSame(403, $this->jsonRequest('POST', '/api/v1/admin/auth/2fa/enrol', headers: $this->bearer($tokens['accessToken']))->getStatusCode(), 'a driver is authenticated but not an admin');

        $this->frozenClock->advance('+15 minutes');
        self::assertSame(401, $this->jsonRequest('POST', '/api/v1/admin/auth/2fa/enrol', headers: $this->bearer($tokens['accessToken']))->getStatusCode());
        self::assertSame('https://slep.example/problems/token-expired', $this->json()['type']);
    }

    public function testAnAdminNeedsTheSecondFactor(): void
    {
        $commandBus = static::getContainer()->get(CommandBus::class);
        $created = $commandBus->dispatch(new CreateAdmin('root@example.com', 'a long admin passphrase'));
        assert($created instanceof CreatedAdmin);
        $secret = $created->enrolment->secret;
        assert('' !== $secret);

        // 1. The password opens a token without roles
        $pending = $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'root@example.com', 'password' => 'a long admin passphrase']);
        self::assertSame(200, $pending->getStatusCode());
        self::assertTrue($this->jsonBool('twoFactorRequired'));
        self::assertArrayNotHasKey('refreshToken', $this->json());
        $pendingToken = $this->jsonString('accessToken');

        // 2. It opens the 2FA endpoints and nothing else
        $confirm = $this->jsonRequest('POST', '/api/v1/admin/auth/2fa/enrol/confirm', ['code' => TotpCodes::at($secret, $this->frozenClock->now()->getTimestamp())], $this->bearer($pendingToken));
        self::assertSame(200, $confirm->getStatusCode());
        self::assertCount(10, $this->jsonList('recoveryCodes'));
        self::assertSame(401, $this->jsonRequest('POST', '/api/v1/auth/refresh', ['refreshToken' => 'x'], $this->bearer($pendingToken))->getStatusCode(), 'unrelated to the token: the refresh token itself is unknown');
        self::assertSame(409, $this->jsonRequest('POST', '/api/v1/admin/auth/2fa/enrol', headers: $this->bearer($pendingToken))->getStatusCode(), 'the pending token reaches the 2FA endpoints (the enrolment is already confirmed)');

        // 3. A code from the next step completes the login
        $this->frozenClock->advance('+30 seconds');
        $verified = $this->jsonRequest('POST', '/api/v1/admin/auth/2fa/verify', ['code' => TotpCodes::at($secret, $this->frozenClock->now()->getTimestamp())], $this->bearer($pendingToken));
        self::assertSame(200, $verified->getStatusCode(), (string) $verified->getContent());
        self::assertArrayHasKey('refreshToken', $this->json());
        $fullToken = $this->jsonString('accessToken');

        // 4. The full token is an admin token: the enrolment is refused only because it already exists
        self::assertSame(409, $this->jsonRequest('POST', '/api/v1/admin/auth/2fa/enrol', headers: $this->bearer($fullToken))->getStatusCode());
    }

    public function testAnAdminCannotLogInWithSocialSignIn(): void
    {
        $commandBus = static::getContainer()->get(CommandBus::class);
        $commandBus->dispatch(new CreateAdmin('root@example.com', 'a long admin passphrase'));
        $this->social->trust('tok', \App\Authentication\Domain\Model\SocialProvider::Google, 'g-1', 'root@example.com');

        self::assertSame(403, $this->jsonRequest('POST', '/api/v1/auth/social/google', ['idToken' => 'tok'])->getStatusCode());
    }

    public function testUnknownMethodsAndMissingJsonAreRefused(): void
    {
        self::assertSame(405, $this->jsonRequest('GET', '/api/v1/auth/login')->getStatusCode());
        $this->client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'text/plain'], content: 'email=a');
        self::assertSame(415, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: '{not json');
        self::assertSame(422, $this->client->getResponse()->getStatusCode());
    }
}
