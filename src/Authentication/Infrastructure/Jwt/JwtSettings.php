<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Jwt;

use InvalidArgumentException;

use function sprintf;

/**
 * Configuration of the access tokens (config/services/authentication.yaml, environment variables).
 */
final readonly class JwtSettings
{
    /**
     * @param non-empty-string                   $privateKeyPath    path of the signing key
     * @param array<array-key, non-empty-string> $publicKeyPaths    public keys by key id; the current one plus previous ones kept so that
     *                                                              tokens signed before a key rotation stay valid until they expire
     * @param non-empty-string                   $currentKeyId      the `kid` written to new tokens
     * @param non-empty-string                   $issuer
     * @param non-empty-string                   $audience
     * @param int                                $ttlSeconds        lifetime of a normal access token
     * @param int                                $pendingTtlSeconds lifetime of an admin's "second factor pending" token
     */
    public function __construct(
        public string $privateKeyPath,
        public array $publicKeyPaths,
        public string $currentKeyId,
        public string $issuer,
        public string $audience,
        public int $ttlSeconds = 900,
        public int $pendingTtlSeconds = 300,
        public ?string $privateKeyPassphrase = null,
    ) {
        foreach (['privateKeyPath' => $privateKeyPath, 'currentKeyId' => $currentKeyId, 'issuer' => $issuer, 'audience' => $audience] as $name => $value) {
            if ('' === trim($value)) {
                throw new InvalidArgumentException(sprintf('The JWT setting "%s" must not be empty.', $name));
            }
        }
    }
}
