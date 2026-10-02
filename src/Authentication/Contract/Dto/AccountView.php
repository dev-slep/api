<?php

declare(strict_types=1);

namespace App\Authentication\Contract\Dto;

use App\SharedKernel\Contract\UserId;

/**
 * What other modules may know about an account.
 */
final readonly class AccountView
{
    public function __construct(
        public UserId $id,
        public string $email,
        public string $role,
        public ?string $phone,
        public string $locale,
        public bool $emailVerified,
        public bool $banned,
    ) {
    }
}
