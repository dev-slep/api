<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Totp;

use App\Authentication\Application\Port\TotpProvisioner;
use App\Authentication\Application\Port\TotpVerifier;
use App\Authentication\Domain\Model\Email;

use function assert;

use DateTimeImmutable;

use function ord;

use OTPHP\TOTP;

use const STR_PAD_LEFT;

/**
 * RFC 6238 time-based one-time passwords: 6 digits, 30-second steps, SHA-1 (what authenticator apps expect).
 * One step of drift either way is accepted; the matching step is returned so the caller can reject replays.
 */
final readonly class OtphpTotp implements TotpVerifier, TotpProvisioner
{
    private const int PERIOD = 30;

    /**
     * @param non-empty-string $issuer
     */
    public function __construct(private string $issuer = 'Slep')
    {
    }

    public function verify(string $secret, string $code, DateTimeImmutable $now): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ('' === $secret || 1 !== preg_match('/^[0-9]{6}$/D', $code)) {
            return null;
        }

        $totp = $this->totp($secret);
        $timestamp = $now->getTimestamp();
        $matched = null;
        // Every step is checked (no early exit), so timing does not reveal which step matched
        foreach ([-1, 0, 1] as $offset) {
            $at = max(0, $timestamp + $offset * self::PERIOD);
            if (hash_equals($totp->at($at), $code)) {
                $matched = intdiv($at, self::PERIOD);
            }
        }

        return $matched;
    }

    /**
     * 160 random bits (the length RFC 4226 recommends), as 32 base32 characters.
     */
    public function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(random_bytes(20)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            $secret .= $alphabet[(int) bindec($chunk)];
        }

        return $secret;
    }

    public function provisioningUri(string $secret, Email $account): string
    {
        $label = $account->toString();
        assert('' !== $label && '' !== $secret);
        $totp = $this->totp($secret);
        $totp->setLabel($label);
        $totp->setIssuer($this->issuer);

        return $totp->getProvisioningUri();
    }

    /**
     * @param non-empty-string $secret
     */
    private function totp(string $secret): TOTP
    {
        return TOTP::createFromSecret($secret, null);
    }
}
