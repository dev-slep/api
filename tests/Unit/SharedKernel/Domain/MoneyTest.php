<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Domain;

use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\CurrencyMismatch;
use App\SharedKernel\Domain\Money;
use App\SharedKernel\Domain\MoneyOverflow;
use Closure;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Money::class)]
#[CoversClass(CurrencyMismatch::class)]
#[CoversClass(MoneyOverflow::class)]
final class MoneyTest extends TestCase
{
    public function testHoldsAmountAndCurrency(): void
    {
        $money = $this->rsd(1500);

        self::assertSame(1500, $money->amount);
        self::assertSame('RSD', $money->currency->code);
    }

    public function testAddReturnsANewInstanceAndLeavesTheOriginalUntouched(): void
    {
        $original = $this->rsd(100);

        $sum = $original->add($this->rsd(250));

        self::assertSame(350, $sum->amount);
        self::assertSame(100, $original->amount);
        self::assertNotSame($original, $sum);
    }

    public function testSubtractCanGoNegative(): void
    {
        self::assertSame(-50, $this->rsd(100)->subtract($this->rsd(150))->amount);
    }

    public function testAddingZeroKeepsTheAmount(): void
    {
        self::assertSame(100, $this->rsd(100)->add($this->rsd(0))->amount);
    }

    public function testAddOverflowThrows(): void
    {
        $this->expectException(MoneyOverflow::class);

        $this->rsd(PHP_INT_MAX)->add($this->rsd(1));
    }

    public function testAddNegativeOverflowThrows(): void
    {
        $this->expectException(MoneyOverflow::class);

        $this->rsd(PHP_INT_MIN)->add($this->rsd(-1));
    }

    public function testAddingUpToTheLimitIsAllowed(): void
    {
        self::assertSame(PHP_INT_MAX, $this->rsd(PHP_INT_MAX - 1)->add($this->rsd(1))->amount);
    }

    public function testSubtractOverflowThrows(): void
    {
        $this->expectException(MoneyOverflow::class);

        $this->rsd(PHP_INT_MIN)->subtract($this->rsd(1));
    }

    public function testSubtractingANegativeOverflowsAtTheUpperLimit(): void
    {
        $this->expectException(MoneyOverflow::class);

        $this->rsd(PHP_INT_MAX)->subtract($this->rsd(-1));
    }

    public function testArithmeticAcrossCurrenciesThrows(): void
    {
        $this->expectException(CurrencyMismatch::class);
        $this->expectExceptionMessage('RSD');

        $this->rsd(1)->add($this->eur(1));
    }

    public function testSubtractAcrossCurrenciesThrows(): void
    {
        $this->expectException(CurrencyMismatch::class);

        $this->rsd(1)->subtract($this->eur(1));
    }

    public function testEqualsRequiresSameAmountAndCurrency(): void
    {
        self::assertTrue($this->rsd(100)->equals($this->rsd(100)));
        self::assertFalse($this->rsd(100)->equals($this->rsd(101)));
        self::assertFalse($this->rsd(100)->equals($this->eur(100)), 'equals() never throws on a currency difference');
    }

    public function testComparisons(): void
    {
        self::assertTrue($this->rsd(2)->isGreaterThan($this->rsd(1)));
        self::assertFalse($this->rsd(1)->isGreaterThan($this->rsd(1)));
        self::assertTrue($this->rsd(1)->isGreaterThanOrEqual($this->rsd(1)));
        self::assertFalse($this->rsd(0)->isGreaterThanOrEqual($this->rsd(1)));
        self::assertTrue($this->rsd(1)->isLessThan($this->rsd(2)));
        self::assertFalse($this->rsd(1)->isLessThan($this->rsd(1)));
        self::assertTrue($this->rsd(1)->isLessThanOrEqual($this->rsd(1)));
        self::assertFalse($this->rsd(2)->isLessThanOrEqual($this->rsd(1)));
    }

    /**
     * @param Closure(Money, Money): bool $comparison
     */
    #[DataProvider('comparisons')]
    public function testComparisonsAcrossCurrenciesThrow(Closure $comparison): void
    {
        $this->expectException(CurrencyMismatch::class);

        $comparison($this->rsd(1), $this->eur(1));
    }

    /**
     * @return iterable<string, array{Closure(Money, Money): bool}>
     */
    public static function comparisons(): iterable
    {
        yield 'isGreaterThan' => [static fn (Money $a, Money $b): bool => $a->isGreaterThan($b)];
        yield 'isGreaterThanOrEqual' => [static fn (Money $a, Money $b): bool => $a->isGreaterThanOrEqual($b)];
        yield 'isLessThan' => [static fn (Money $a, Money $b): bool => $a->isLessThan($b)];
        yield 'isLessThanOrEqual' => [static fn (Money $a, Money $b): bool => $a->isLessThanOrEqual($b)];
    }

    public function testSignPredicates(): void
    {
        self::assertTrue($this->rsd(1)->isPositive());
        self::assertFalse($this->rsd(0)->isPositive());
        self::assertFalse($this->rsd(-1)->isPositive());

        self::assertTrue($this->rsd(0)->isZero());
        self::assertFalse($this->rsd(1)->isZero());
        self::assertFalse($this->rsd(-1)->isZero());
    }

    private function rsd(int $amount): Money
    {
        return new Money($amount, new Currency('RSD'));
    }

    private function eur(int $amount): Money
    {
        return new Money($amount, new Currency('EUR'));
    }
}
