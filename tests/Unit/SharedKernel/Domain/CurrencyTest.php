<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Domain;

use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\InvalidCurrency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Currency::class)]
#[CoversClass(InvalidCurrency::class)]
final class CurrencyTest extends TestCase
{
    public function testHoldsTheCode(): void
    {
        self::assertSame('RSD', (new Currency('RSD'))->code);
    }

    #[DataProvider('invalidCodes')]
    public function testRejectsInvalidCodes(string $code): void
    {
        $this->expectException(InvalidCurrency::class);

        new Currency($code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'lowercase' => ['rsd'];
        yield 'mixed case' => ['Rsd'];
        yield 'two letters' => ['RS'];
        yield 'four letters' => ['RSDD'];
        yield 'digits' => ['R5D'];
        yield 'trailing newline' => ["RSD\n"];
    }

    public function testErrorMessageContainsTheOffendingCode(): void
    {
        $this->expectExceptionMessage('"abc"');

        new Currency('abc');
    }

    public function testEqualsComparesCodes(): void
    {
        self::assertTrue((new Currency('RSD'))->equals(new Currency('RSD')));
        self::assertFalse((new Currency('RSD'))->equals(new Currency('EUR')));
    }
}
