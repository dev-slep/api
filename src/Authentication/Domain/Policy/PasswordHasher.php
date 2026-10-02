<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Policy;

use App\Authentication\Domain\Model\PasswordHash;
use App\Authentication\Domain\Model\PlainPassword;

interface PasswordHasher
{
    public function hash(PlainPassword $password): PasswordHash;

    public function verify(PlainPassword $password, PasswordHash $hash): bool;

    /**
     * True when the hash was made with weaker parameters than the current ones and should be recomputed on login.
     */
    public function needsRehash(PasswordHash $hash): bool;
}
