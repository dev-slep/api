<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\Penalty\Contract\BlacklistChecker;

use function in_array;

final class FakeBlacklistChecker implements BlacklistChecker
{
    /** @var list<string> */
    public array $blocked = [];

    public function isBlacklisted(?string $email, ?string $phone): bool
    {
        return (null !== $email && in_array($email, $this->blocked, true)) || (null !== $phone && in_array($phone, $this->blocked, true));
    }
}
