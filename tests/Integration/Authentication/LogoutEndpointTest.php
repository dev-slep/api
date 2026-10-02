<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Contract\Event\UserLoggedOutV1;
use App\Authentication\Infrastructure\Http\Controller\LogoutController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LogoutController::class)]
#[CoversEndpoint('POST', '/api/v1/auth/logout')]
final class LogoutEndpointTest extends AuthenticationIntegrationTestCase
{
    public function testLoggingOutRevokesTheSessionButNotTheOthers(): void
    {
        $account = $this->createAccount();
        $phone = $this->login($account->email()->toString());
        $laptop = $this->login($account->email()->toString());

        $response = $this->call('POST', '/api/v1/auth/logout', ['refreshToken' => $phone['refreshToken']]);

        self::assertSame(204, $response->getStatusCode());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/logout');
        self::assertSame('', (string) $response->getContent());
        self::assertSame(1, $this->countRows('authentication.refresh_token', 'revoked_at IS NOT NULL'));
        self::assertSame(401, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $phone['refreshToken']])->getStatusCode());
        self::assertSame(200, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $laptop['refreshToken']])->getStatusCode());
        $this->assertEventStored(UserLoggedOutV1::class);
    }

    public function testLoggingOutOfAllDevicesRevokesEverySession(): void
    {
        $account = $this->createAccount();
        $phone = $this->login($account->email()->toString());
        $laptop = $this->login($account->email()->toString());
        $stranger = $this->login($this->createAccount()->email()->toString());

        self::assertSame(204, $this->call('POST', '/api/v1/auth/logout', ['refreshToken' => $phone['refreshToken'], 'allDevices' => true])->getStatusCode());

        self::assertSame(401, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $laptop['refreshToken']])->getStatusCode());
        self::assertSame(200, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $stranger['refreshToken']])->getStatusCode(), 'other accounts are not affected');
    }

    public function testLoggingOutIsIdempotent(): void
    {
        $tokens = $this->login($this->createAccount()->email()->toString());

        foreach ([1, 2, 3] as $attempt) {
            self::assertSame(204, $this->call('POST', '/api/v1/auth/logout', ['refreshToken' => $tokens['refreshToken']])->getStatusCode(), "attempt $attempt");
        }
        self::assertSame(204, $this->call('POST', '/api/v1/auth/logout', ['refreshToken' => 'never-issued'])->getStatusCode());
    }

    public function testAnEmptyOrMissingTokenIsRefused(): void
    {
        foreach ([[], ['refreshToken' => ''], ['refreshToken' => str_repeat('a', 257)], ['refreshToken' => 'x', 'allDevices' => 'maybe']] as $body) {
            $response = $this->call('POST', '/api/v1/auth/logout', $body);
            self::assertSame(422, $response->getStatusCode(), json_encode($body, JSON_THROW_ON_ERROR));
            self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/logout');
        }
    }
}
