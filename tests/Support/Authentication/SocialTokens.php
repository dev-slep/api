<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\Tests\Support\Fake\FrozenClock;

use const ARRAY_FILTER_USE_KEY;

use function is_array;
use function is_string;

use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use LogicException;

use const OPENSSL_KEYTYPE_RSA;

use OpenSSLAsymmetricKey;

/**
 * Signs Google and Apple style ID tokens with the test key whose public half WireMock publishes as the providers'
 * signing keys (docker/wiremock/__files/*-jwks.json).
 */
final class SocialTokens
{
    private const string KEY_FILE = __DIR__.'/../Fixtures/Social/test-signing-key.pem';
    public const string GOOGLE_AUDIENCE = 'google-client-id.apps.googleusercontent.com';
    public const string APPLE_AUDIENCE = 'com.slep.app';

    /** The raw nonce the tests send; the tokens carry its SHA-256 digest */
    public const string NONCE = 'test-nonce-0123456789abcdef';

    private ?OpenSSLAsymmetricKey $rotatedKey = null;

    public function __construct(private readonly FrozenClock $clock)
    {
    }

    /**
     * @param 'test'|'rotated'|'other' $signWith
     */
    public function google(string $email, string $subject, bool $emailVerified = true, string $audience = self::GOOGLE_AUDIENCE, string $issuer = 'https://accounts.google.com', string $expiresIn = '+1 hour', string $keyId = 'test-key-1', string $signWith = 'test'): string
    {
        return $this->token($email, $subject, $emailVerified, $audience, $issuer, $expiresIn, $keyId, $signWith);
    }

    /**
     * Apple sends `email_verified` as the string "true" or "false".
     *
     * @param 'test'|'rotated'|'other' $signWith
     */
    public function apple(string $email, string $subject, bool $emailVerified = true, string $audience = self::APPLE_AUDIENCE, string $issuer = 'https://appleid.apple.com', string $expiresIn = '+1 hour', string $keyId = 'test-key-1', string $signWith = 'test'): string
    {
        return $this->token($email, $subject, $emailVerified ? 'true' : 'false', $audience, $issuer, $expiresIn, $keyId, $signWith);
    }

    /**
     * The published key set with one more key: the one used when signing with "rotated".
     *
     * @return array{keys: list<array<string, string>>}
     */
    public function jwksWithAdditionalKey(string $keyId): array
    {
        $published = json_decode((string) file_get_contents(__DIR__.'/../../../docker/wiremock/__files/google-jwks.json'), true);
        if (!is_array($published) || !is_array($published['keys'] ?? null)) {
            throw new LogicException('The published key set is malformed.');
        }

        $keys = [];
        foreach ($published['keys'] as $key) {
            if (!is_array($key)) {
                continue;
            }
            $keys[] = array_map(static fn (mixed $value): string => is_string($value) ? $value : '', array_filter($key, 'is_string', ARRAY_FILTER_USE_KEY));
        }

        $details = openssl_pkey_get_details($this->rotatedKey());
        if (false === $details) {
            throw new LogicException('The rotated key has no details.');
        }
        $rsa = $details['rsa'] ?? null;
        if (!is_array($rsa) || !is_string($rsa['n'] ?? null) || !is_string($rsa['e'] ?? null)) {
            throw new LogicException('The rotated key is not an RSA key.');
        }
        $keys[] = ['kty' => 'RSA', 'kid' => $keyId, 'use' => 'sig', 'alg' => 'RS256', 'n' => $this->base64url($rsa['n']), 'e' => $this->base64url($rsa['e'])];

        return ['keys' => $keys];
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function rotatedKey(): OpenSSLAsymmetricKey
    {
        return $this->rotatedKey ??= $this->newKey();
    }

    private function newKey(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if (false === $key) {
            throw new LogicException('A test key could not be generated.');
        }

        return $key;
    }

    private function token(string $email, string $subject, bool|string $emailVerified, string $audience, string $issuer, string $expiresIn, string $keyId, string $signWith): string
    {
        $pem = match ($signWith) {
            'test' => (string) file_get_contents(self::KEY_FILE),
            'rotated' => $this->exportKey($this->rotatedKey()),
            default => $this->exportKey($this->newKey()),
        };
        if ('' === $pem || '' === $email || '' === $subject || '' === $audience || '' === $issuer || '' === $keyId) {
            throw new LogicException('A token needs a key, an email, a subject, an audience, an issuer and a key id.');
        }

        return Builder::new(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates())
            ->issuedBy($issuer)
            ->permittedFor($audience)
            ->relatedTo($subject)
            ->issuedAt($this->clock->now())
            ->expiresAt($this->clock->now()->modify($expiresIn))
            ->withHeader('kid', $keyId)
            ->withClaim('nonce', hash('sha256', self::NONCE))
            ->withClaim('email', $email)
            ->withClaim('email_verified', $emailVerified)
            ->getToken(new Sha256(), InMemory::plainText($pem))
            ->toString();
    }

    private function exportKey(OpenSSLAsymmetricKey $key): string
    {
        openssl_pkey_export($key, $pem);

        return is_string($pem) ? $pem : '';
    }
}
