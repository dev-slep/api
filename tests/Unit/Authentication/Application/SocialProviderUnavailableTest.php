<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Application;

use App\Authentication\Application\Port\SocialProviderUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SocialProviderUnavailable::class)]
final class SocialProviderUnavailableTest extends TestCase
{
    public function testItIsA503Problem(): void
    {
        $failure = new SocialProviderUnavailable();

        self::assertSame('social-provider-unavailable', $failure->problemSlug());
        self::assertSame(503, $failure->httpStatus());
        self::assertSame('The sign-in provider is not reachable.', $failure->getMessage());
    }

    public function testItKeepsItsMessageAndCause(): void
    {
        $cause = new RuntimeException('connection refused');

        $failure = new SocialProviderUnavailable('keys could not be fetched', $cause);

        self::assertSame('keys could not be fetched', $failure->getMessage());
        self::assertSame($cause, $failure->getPrevious());
    }
}
