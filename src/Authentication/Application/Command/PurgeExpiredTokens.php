<?php

declare(strict_types=1);

namespace App\Authentication\Application\Command;

use App\SharedKernel\Application\Command;

/**
 * Deletes refresh, verification and reset tokens that expired longer ago than the configured retention.
 */
final readonly class PurgeExpiredTokens implements Command
{
}
