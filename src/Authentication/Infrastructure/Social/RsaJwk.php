<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Social;

use function chr;
use function ord;

use RuntimeException;

use function strlen;

/**
 * Converts the RSA public key of a JSON Web Key (modulus `n`, exponent `e`, base64url) to PEM, which the JWT
 * library reads. Google and Apple publish their signing keys this way.
 */
final readonly class RsaJwk
{
    /**
     * @return non-empty-string
     */
    public static function toPem(string $n, string $e): string
    {
        $modulus = self::decode($n);
        $exponent = self::decode($e);

        $rsaPublicKey = self::sequence(self::integer($modulus).self::integer($exponent));
        // AlgorithmIdentifier: rsaEncryption (1.2.840.113549.1.1.1) with NULL parameters
        $algorithm = self::sequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $subjectPublicKeyInfo = self::sequence($algorithm."\x03".self::length(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if (false === $decoded || '' === $decoded) {
            throw new RuntimeException('The JSON Web Key is malformed.');
        }

        return $decoded;
    }

    private static function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ('' === $bytes || ord($bytes[0]) > 0x7F) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".self::length(strlen($bytes)).$bytes;
    }

    private static function sequence(string $content): string
    {
        return "\x30".self::length(strlen($content)).$content;
    }

    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length & 0xFF);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr((0x80 | strlen($bytes)) & 0xFF).$bytes;
    }
}
