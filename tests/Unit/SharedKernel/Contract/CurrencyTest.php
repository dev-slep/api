<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Contract;

use App\SharedKernel\Contract\Currency;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Currency::class)]
final class CurrencyTest extends TestCase
{
    public function testHoldsTheCode(): void
    {
        $currency = new Currency('RSD');

        self::assertSame('RSD', $currency->code);
        self::assertSame('RSD', (string) $currency);
    }

    #[DataProvider('invalidCodes')]
    public function testRejectsInvalidCodes(string $code): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency($code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'lowercase' => ['rsd'];
        yield 'two letters' => ['RS'];
        yield 'four letters' => ['RSDD'];
        yield 'digits' => ['R5D'];
        yield 'trailing newline' => ["RSD\n"];
    }

    public function testEquals(): void
    {
        self::assertTrue((new Currency('RSD'))->equals(new Currency('RSD')));
        self::assertFalse((new Currency('RSD'))->equals(new Currency('EUR')));
    }
}
