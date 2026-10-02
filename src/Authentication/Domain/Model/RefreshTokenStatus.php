<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

enum RefreshTokenStatus
{
    case Usable;
    case Expired;
    /** Already exchanged for a newer token: presenting it again means it was copied. */
    case Rotated;
    case Revoked;
}
