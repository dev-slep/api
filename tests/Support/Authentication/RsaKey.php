<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use function is_array;
use function is_string;

use LogicException;

use const OPENSSL_KEYTYPE_RSA;

use OpenSSLAsymmetricKey;

/**
 * A freshly generated RSA key pair for tests (2048 bits), with the PEM and JWK forms the tests need.
 */
final readonly class RsaKey
{
    private function __construct(private OpenSSLAsymmetricKey $key)
    {
    }

    public static function generate(): self
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if (false === $key) {
            throw new LogicException('A test key could not be generated.');
        }

        return new self($key);
    }

    /**
     * @return non-empty-string
     */
    public function privatePem(): string
    {
        openssl_pkey_export($this->key, $pem);
        if (!is_string($pem) || '' === $pem) {
            throw new LogicException('The private key could not be exported.');
        }

        return $pem;
    }

    /**
     * @return non-empty-string
     */
    public function publicPem(): string
    {
        $pem = $this->details()['key'] ?? null;
        if (!is_string($pem) || '' === $pem) {
            throw new LogicException('The public key could not be exported.');
        }

        return $pem;
    }

    /**
     * The public key as a JSON Web Key, the way Google and Apple publish their signing keys.
     *
     * @param non-empty-string $keyId
     *
     * @return array{kty: string, kid: string, use: string, alg: string, n: string, e: string}
     */
    public function jwk(string $keyId): array
    {
        $rsa = $this->details()['rsa'] ?? null;
        if (!is_array($rsa) || !is_string($rsa['n'] ?? null) || !is_string($rsa['e'] ?? null)) {
            throw new LogicException('The key is not an RSA key.');
        }

        return ['kty' => 'RSA', 'kid' => $keyId, 'use' => 'sig', 'alg' => 'RS256', 'n' => self::base64url($rsa['n']), 'e' => self::base64url($rsa['e'])];
    }

    /**
     * @return array<mixed>
     */
    private function details(): array
    {
        $details = openssl_pkey_get_details($this->key);
        if (false === $details) {
            throw new LogicException('The key has no details.');
        }

        return $details;
    }

    public static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
