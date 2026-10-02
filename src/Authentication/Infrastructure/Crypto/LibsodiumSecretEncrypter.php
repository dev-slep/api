<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Crypto;

use App\Authentication\Domain\Policy\SecretEncrypter;
use RuntimeException;

use const SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;
use const SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

use function strlen;

/**
 * XChaCha20-Poly1305 authenticated encryption with a key from the environment (`AUTH_ENCRYPTION_KEY`, 32 bytes, base64).
 * Rotating the key means re-encrypting the stored secrets (admins would otherwise have to enrol again).
 */
final readonly class LibsodiumSecretEncrypter implements SecretEncrypter
{
    private string $key;

    public function __construct(string $base64Key)
    {
        $key = base64_decode($base64Key, true);
        if (false === $key || SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== strlen($key)) {
            throw new RuntimeException('AUTH_ENCRYPTION_KEY must be 32 random bytes, base64 encoded.');
        }

        $this->key = $key;
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        return base64_encode($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, '', $nonce, $this->key));
    }

    public function decrypt(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (false === $raw || strlen($raw) <= $nonceLength) {
            throw new RuntimeException('The encrypted value is malformed.');
        }

        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, $nonceLength), '', substr($raw, 0, $nonceLength), $this->key);
        if (false === $plain) {
            throw new RuntimeException('The encrypted value could not be decrypted.');
        }

        return $plain;
    }
}
