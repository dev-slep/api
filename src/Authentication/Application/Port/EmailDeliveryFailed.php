<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use RuntimeException;

final class EmailDeliveryFailed extends RuntimeException
{
}
