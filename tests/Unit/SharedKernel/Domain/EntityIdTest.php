<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Domain;

use App\SharedKernel\Domain\EntityId;
use App\SharedKernel\Domain\InvalidIdentifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final readonly class SomeId extends EntityId
{
}

final readonly class OtherId extends EntityId
{
}

#[CoversClass(EntityId::class)]
#[CoversClass(InvalidIdentifier::class)]
final class EntityIdTest extends TestCase
{
    private const string UUID = '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b';

    public function testHoldsAValidUuid(): void
    {
        $id = new SomeId(self::UUID);

        self::assertSame(self::UUID, $id->toString());
        self::assertSame(self::UUID, (string) $id);
    }

    public function testUppercaseInputIsNormalisedToLowercase(): void
    {
        self::assertSame(self::UUID, (new SomeId(strtoupper(self::UUID)))->toString());
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(string $value): void
    {
        $this->expectException(InvalidIdentifier::class);

        new SomeId($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'not hex' => ['zzzzzzzz-zzzz-zzzz-zzzz-zzzzzzzzzzzz'];
        yield 'too short' => ['0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6'];
        yield 'too long' => ['0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b0'];
        yield 'no dashes' => ['0190a1b2c3d47e5f8a6b1c2d3e4f5a6b'];
        yield 'surrounding whitespace' => [' 0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b '];
        yield 'trailing newline' => ["0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b\n"];
    }

    public function testErrorMessageContainsTheOffendingValue(): void
    {
        $this->expectExceptionMessage('"nope"');

        new SomeId('nope');
    }

    public function testEqualsComparesValueAndType(): void
    {
        $id = new SomeId(self::UUID);

        self::assertTrue($id->equals(new SomeId(self::UUID)));
        self::assertTrue($id->equals(new SomeId(strtoupper(self::UUID))));
        self::assertFalse($id->equals(new SomeId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6c')));
        self::assertFalse($id->equals(new OtherId(self::UUID)), 'ids of different types are never equal');
    }
}
