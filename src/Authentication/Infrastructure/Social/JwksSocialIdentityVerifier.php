<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Social;

use App\Authentication\Application\Port\SocialIdentityVerifier;
use App\Authentication\Application\Port\SocialProviderUnavailable;
use App\Authentication\Application\Port\VerifiedSocialIdentity;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Exception\InvalidValue;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use App\SharedKernel\Domain\Clock;

use function count;

use const FILTER_VALIDATE_BOOLEAN;

use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_string;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

use function sprintf;
use function strtolower;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Verifies the OpenID Connect ID token of Google or Apple: signature (against the provider's published keys,
 * fetched over HTTP and cached), issuer, audience and expiry. A token signed with a key the provider has
 * rotated away is retried once with fresh keys.
 */
final readonly class JwksSocialIdentityVerifier implements SocialIdentityVerifier
{
    private const int CACHE_SECONDS = 3600;
    private const int CLOCK_SKEW_SECONDS = 60;

    /**
     * @param array<string, SocialProviderSettings> $providers keyed by provider value ("google", "apple")
     */
    public function __construct(
        private array $providers,
        private HttpClientInterface $http,
        private CacheInterface $cache,
        private Clock $clock,
    ) {
    }

    public function verify(SocialProvider $provider, string $idToken, string $nonce): VerifiedSocialIdentity
    {
        $settings = $this->providers[$provider->value] ?? throw AuthenticationProblem::socialTokenInvalid();
        if ('' === $idToken || '' === $nonce) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        try {
            $token = (new Parser(new JoseEncoder()))->parse($idToken);
        } catch (Throwable) {
            throw AuthenticationProblem::socialTokenInvalid();
        }
        if (!$token instanceof UnencryptedToken) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        $keyId = $token->headers()->get('kid');
        if (!is_string($keyId)) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        $key = $this->findKey($provider, $settings, $keyId, false) ?? $this->findKey($provider, $settings, $keyId, true);
        if (null === $key) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        if (!(new Validator())->validate($token, new SignedWith(new Sha256(), InMemory::plainText($key)))) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        $claims = $token->claims();
        $issuer = $claims->get('iss');
        $audience = $claims->get('aud');
        $audiences = array_filter(is_array($audience) ? $audience : [$audience], is_string(...));
        if (!is_string($issuer) || !in_array($issuer, $settings->issuers, true) || [] === array_intersect($audiences, $settings->audiences)) {
            throw AuthenticationProblem::socialTokenInvalid();
        }
        // Required, not just checked when present: a token without `exp` would never expire
        if (!$claims->has(RegisteredClaims::EXPIRATION_TIME) || !$claims->has(RegisteredClaims::ISSUED_AT)) {
            throw AuthenticationProblem::socialTokenInvalid();
        }
        $now = $this->clock->now();
        if ($token->isExpired($now) || !$token->hasBeenIssuedBefore($now->modify(sprintf('+%d seconds', self::CLOCK_SKEW_SECONDS)))) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        // The authorized party must be one of the app's own client ids; with several audiences it is mandatory
        $authorizedParty = $claims->get('azp');
        if (null !== $authorizedParty && (!is_string($authorizedParty) || !in_array($authorizedParty, $settings->audiences, true))) {
            throw AuthenticationProblem::socialTokenInvalid();
        }
        if (null === $authorizedParty && count($audiences) > 1) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        // The client hands the provider the SHA-256 of its nonce: the token proves it was requested by whoever knows
        // the raw value, so a token that leaked on its own can't be replayed. The raw nonce itself is never accepted,
        // because it would be readable from the token.
        $tokenNonce = $claims->get('nonce');
        if (!is_string($tokenNonce) || !hash_equals(hash('sha256', $nonce), strtolower($tokenNonce))) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        $subject = $claims->get('sub');
        $email = $claims->get('email');
        if (!is_string($subject) || !is_string($email)) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        try {
            $verifiedEmail = new Email($email);
            $identity = new SocialIdentity($provider, new SocialSubject($subject));
        } catch (InvalidValue) {
            throw AuthenticationProblem::socialTokenInvalid();
        }

        // Apple sends "true"/"false" as strings, Google sends booleans
        $emailVerified = filter_var($claims->get('email_verified', false), FILTER_VALIDATE_BOOLEAN);

        $privateRelay = filter_var($claims->get('is_private_email', false), FILTER_VALIDATE_BOOLEAN);

        return new VerifiedSocialIdentity($identity, $verifiedEmail, $emailVerified, $privateRelay);
    }

    /**
     * @return non-empty-string|null
     */
    private function findKey(SocialProvider $provider, SocialProviderSettings $settings, string $keyId, bool $refresh): ?string
    {
        $keys = $this->keys($provider, $settings, $refresh);

        return $keys[$keyId] ?? null;
    }

    /**
     * @return array<string, non-empty-string> PEM public keys by key id
     */
    private function keys(SocialProvider $provider, SocialProviderSettings $settings, bool $refresh): array
    {
        $cacheKey = 'authentication_jwks_'.$provider->value;
        if ($refresh) {
            $this->cache->delete($cacheKey);
        }

        try {
            /** @var array<string, non-empty-string> $keys */
            $keys = $this->cache->get($cacheKey, function (ItemInterface $item) use ($settings): array {
                $item->expiresAfter(self::CACHE_SECONDS);

                return $this->fetch($settings);
            });
        } catch (HttpClientException $failure) {
            throw new SocialProviderUnavailable('The sign-in provider keys could not be fetched.', $failure);
        }

        return $keys;
    }

    /**
     * @return array<string, non-empty-string>
     */
    private function fetch(SocialProviderSettings $settings): array
    {
        $response = $this->http->request('GET', $settings->jwksUrl, ['timeout' => 5]);
        $data = $response->toArray();
        $jwks = $data['keys'] ?? null;
        if (!is_array($jwks)) {
            throw new SocialProviderUnavailable('The provider key set is malformed.');
        }

        $keys = [];
        foreach ($jwks as $jwk) {
            if (!is_array($jwk) || 'RSA' !== ($jwk['kty'] ?? null) || !is_string($jwk['kid'] ?? null) || !is_string($jwk['n'] ?? null) || !is_string($jwk['e'] ?? null)) {
                continue;
            }
            try {
                $keys[$jwk['kid']] = RsaJwk::toPem($jwk['n'], $jwk['e']);
            } catch (Throwable) {
                continue;
            }
        }
        if ([] === $keys) {
            throw new SocialProviderUnavailable('The provider key set holds no usable key.');
        }

        return $keys;
    }
}
