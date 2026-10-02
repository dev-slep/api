<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Crypto;

use App\Authentication\Domain\Model\OpaqueToken;
use App\Authentication\Domain\Policy\TokenGenerator;

use function strlen;

final readonly class RandomTokenGenerator implements TokenGenerator
{
    private const string ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789';

    public function generate(): OpaqueToken
    {
        return new OpaqueToken(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public function recoveryCode(): string
    {
        $groups = [];
        for ($group = 0; $group < 3; ++$group) {
            $part = '';
            for ($i = 0; $i < 4; ++$i) {
                $part .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groups[] = $part;
        }

        return implode('-', $groups);
    }
}
