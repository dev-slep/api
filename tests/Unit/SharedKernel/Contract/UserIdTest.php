<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Contract;

use App\SharedKernel\Contract\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserId::class)]
final class UserIdTest extends TestCase
{
    private const string UUID = '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b';

    public function testHoldsAValidUuid(): void
    {
        $id = new UserId(self::UUID);

        self::assertSame(self::UUID, $id->toString());
        self::assertSame(self::UUID, (string) $id);
    }

    public function testNormalisesToLowercase(): void
    {
        self::assertSame(self::UUID, new UserId(strtoupper(self::UUID))->toString());
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UserId($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'not hex' => ['zzzzzzzz-zzzz-zzzz-zzzz-zzzzzzzzzzzz'];
        yield 'too short' => ['0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6'];
        yield 'no dashes' => ['0190a1b2c3d47e5f8a6b1c2d3e4f5a6b'];
        yield 'trailing newline' => ["0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b\n"];
    }

    public function testEquals(): void
    {
        self::assertTrue(new UserId(self::UUID)->equals(new UserId(strtoupper(self::UUID))));
        self::assertFalse(new UserId(self::UUID)->equals(new UserId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6c')));
    }
}
