<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Id;

use App\SharedKernel\Infrastructure\Id\UuidV7IdGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UuidV7IdGenerator::class)]
final class UuidV7IdGeneratorTest extends TestCase
{
    public function testGeneratesVersion7Uuids(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            (new UuidV7IdGenerator())->generate(),
        );
    }

    public function testGeneratesUniqueValues(): void
    {
        $generator = new UuidV7IdGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }
}
