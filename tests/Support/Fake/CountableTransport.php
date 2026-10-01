<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * A transport that can report its message count (like the Doctrine transport), for doubling in unit tests.
 */
interface CountableTransport extends TransportInterface, MessageCountAwareInterface
{
}
