<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Crypto;

use App\Authentication\Domain\Model\PasswordHash;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Domain\Policy\PasswordHasher;

use const PASSWORD_ARGON2ID;

/**
 * Argon2id through PHP's password_hash (libsodium). The parameters come from configuration;
 * the test environment lowers them so the suites stay fast.
 */
final readonly class Argon2idPasswordHasher implements PasswordHasher
{
    public function __construct(
        private int $memoryCostKib = 65536,
        private int $timeCost = 4,
        private int $threads = 1,
    ) {
    }

    public function hash(PlainPassword $password): PasswordHash
    {
        return new PasswordHash(password_hash($password->reveal(), PASSWORD_ARGON2ID, $this->options()));
    }

    public function verify(PlainPassword $password, PasswordHash $hash): bool
    {
        return password_verify($password->reveal(), $hash->value());
    }

    public function needsRehash(PasswordHash $hash): bool
    {
        return password_needs_rehash($hash->value(), PASSWORD_ARGON2ID, $this->options());
    }

    /**
     * @return array{memory_cost: int, time_cost: int, threads: int}
     */
    private function options(): array
    {
        return ['memory_cost' => $this->memoryCostKib, 'time_cost' => $this->timeCost, 'threads' => $this->threads];
    }
}
