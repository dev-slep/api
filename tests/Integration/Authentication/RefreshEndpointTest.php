<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Contract\Event\RefreshTokenReuseDetectedV1;
use App\Authentication\Infrastructure\Http\Controller\RefreshController;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RefreshController::class)]
#[CoversEndpoint('POST', '/api/v1/auth/refresh')]
final class RefreshEndpointTest extends AuthenticationIntegrationTestCase
{
    public function testRefreshingRotatesTheTokenWithinItsFamily(): void
    {
        $account = $this->createAccount();
        $first = $this->login($account->email()->toString());

        $response = $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $first['refreshToken']]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesOpenApiSchema($response, 'POST', '/api/v1/auth/refresh');
        $second = $this->json();
        self::assertNotSame($first['refreshToken'], $second['refreshToken']);
        self::assertNotSame($first['accessToken'], $second['accessToken']);

        $rows = $this->connection()->fetchAllAssociative('SELECT * FROM authentication.refresh_token ORDER BY issued_at, rotated_at NULLS LAST');
        self::assertCount(2, $rows);
        self::assertSame($rows[0]['family_id'], $rows[1]['family_id']);
        $byHash = [];
        foreach ($rows as $row) {
            self::assertIsString($row['hash']);
            $byHash[$row['hash']] = $row;
        }
        $old = $byHash[hash('sha256', $first['refreshToken'])] ?? self::fail('The first token is not stored.');
        $new = $byHash[hash('sha256', $this->jsonString('refreshToken'))] ?? self::fail('The second token is not stored.');
        self::assertNotNull($old['rotated_at']);
        self::assertSame($new['id'], $old['replaced_by']);
        self::assertNull($new['rotated_at']);
        self::assertEquals(2, $old['version'], 'the optimistic lock counted the rotation');
    }

    public function testTheNewAccessTokenCarriesTheCurrentRoles(): void
    {
        $account = $this->createAccount();
        $first = $this->login($account->email()->toString());

        $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $first['refreshToken']]);

        $claims = $this->claims($this->jsonString('accessToken'));
        self::assertSame(['ROLE_DRIVER'], $claims['roles']);
        self::assertSame($account->id()->toString(), $claims['sub']);
    }

    public function testReusingARotatedTokenRevokesTheWholeFamilyAndRecordsAnEvent(): void
    {
        $account = $this->createAccount();
        $first = $this->login($account->email()->toString());
        $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $first['refreshToken']]);
        $second = $this->json();
        $other = $this->login($account->email()->toString());

        $reuse = $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $first['refreshToken']]);

        self::assertSame(401, $reuse->getStatusCode());
        self::assertMatchesOpenApiSchema($reuse, 'POST', '/api/v1/auth/refresh');
        self::assertSame('https://slep.example/problems/token-revoked', $this->json()['type']);
        self::assertSame(401, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $second['refreshToken']])->getStatusCode(), 'the newest token of the family is cut off too');
        self::assertSame(200, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $other['refreshToken']])->getStatusCode(), 'another session of the same account is not affected');
        $this->assertEventStored(RefreshTokenReuseDetectedV1::class);
        self::assertSame(2, $this->countRows('authentication.refresh_token', 'revoked_at IS NOT NULL'), 'both tokens of the stolen family are revoked');
    }

    public function testAnExpiredTokenIsRefusedExactlyAtItsExpiry(): void
    {
        $clock = $this->freezeClock();
        $account = $this->createAccount();
        $tokens = $this->login($account->email()->toString());

        $clock->advance('+30 days -1 second');
        self::assertSame(200, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']])->getStatusCode(), 'one second before the expiry');

        $other = $this->login($account->email()->toString());
        $clock->advance('+30 days');
        $expired = $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $other['refreshToken']]);

        self::assertSame(401, $expired->getStatusCode());
        self::assertSame('https://slep.example/problems/token-expired', $this->json()['type']);
    }

    public function testUnknownMalformedAndEmptyTokensAreRefused(): void
    {
        self::assertSame(401, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => 'x'])->getStatusCode());
        self::assertSame(401, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => str_repeat('z', 100)])->getStatusCode());
        $empty = $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => '']);
        self::assertSame(422, $empty->getStatusCode());
        self::assertMatchesOpenApiSchema($empty, 'POST', '/api/v1/auth/refresh');
        self::assertSame(422, $this->call('POST', '/api/v1/auth/refresh', [])->getStatusCode());
        self::assertSame(422, $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => str_repeat('a', 257)])->getStatusCode());
    }

    public function testABannedAccountCannotRefreshAndLosesItsFamily(): void
    {
        $account = $this->createAccount();
        $tokens = $this->login($account->email()->toString());
        $this->rawUpdate("UPDATE authentication.user_account SET status = 'BANNED' WHERE id = :id", ['id' => $account->id()->toString()]);

        $response = $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/account-banned', $this->json()['type']);
        self::assertSame(1, $this->countRows('authentication.refresh_token', 'revoked_at IS NOT NULL'));
    }

    public function testATokenTravelsInTheBodyNotTheAuthorizationHeader(): void
    {
        $account = $this->createAccount();
        $tokens = $this->login($account->email()->toString());

        $response = $this->call('POST', '/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']], $this->bearer('garbage'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/token-invalid', $this->json()['type'], 'a bad Authorization header is rejected before the body is read');
    }
}
