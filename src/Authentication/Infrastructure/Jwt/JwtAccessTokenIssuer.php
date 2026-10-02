<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Jwt;

use App\Authentication\Application\Port\AccessTokenIssuer;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Application\Port\IssuedAccessToken;
use App\Authentication\Domain\Model\AccountId;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;

use function assert;

use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;

use function sprintf;

/**
 * Signs short-lived RS256 access tokens. Claims: sub (account id), roles, amr (how the user authenticated),
 * iss, aud, iat, nbf, exp and jti; the header carries the `kid` of the signing key.
 */
final readonly class JwtAccessTokenIssuer implements AccessTokenIssuer
{
    public function __construct(
        private JwtSettings $settings,
        private Clock $clock,
        private IdGenerator $ids,
    ) {
    }

    public function issue(AccountId $accountId, array $roles, AuthenticationMethod $method): IssuedAccessToken
    {
        $now = $this->clock->now();
        $ttl = AuthenticationMethod::PendingTwoFactor === $method ? $this->settings->pendingTtlSeconds : $this->settings->ttlSeconds;

        $id = $this->ids->generate();
        $subject = $accountId->toString();
        assert('' !== $id && '' !== $subject);
        $key = InMemory::file($this->settings->privateKeyPath, $this->settings->privateKeyPassphrase ?? '');
        $token = Builder::new(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates())
            ->issuedBy($this->settings->issuer)
            ->permittedFor($this->settings->audience)
            ->identifiedBy($id)
            ->relatedTo($subject)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify(sprintf('+%d seconds', $ttl)))
            ->withHeader('kid', $this->settings->currentKeyId)
            ->withClaim('roles', $roles)
            ->withClaim('amr', $method->value)
            ->getToken(new Sha256(), $key);

        return new IssuedAccessToken($token->toString(), $ttl);
    }
}
