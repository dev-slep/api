<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

use function sprintf;

/**
 * An amount in minor units (e.g. para/cents) of a currency. Immutable.
 */
final readonly class Money
{
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        $overflows = $other->amount > 0
            ? $this->amount > PHP_INT_MAX - $other->amount
            : $this->amount < PHP_INT_MIN - $other->amount;
        if ($overflows) {
            throw MoneyOverflow::whileCalculating(sprintf('%d + %d', $this->amount, $other->amount));
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        $overflows = $other->amount > 0
            ? $this->amount < PHP_INT_MIN + $other->amount
            : $this->amount > PHP_INT_MAX + $other->amount;
        if ($overflows) {
            throw MoneyOverflow::whileCalculating(sprintf('%d - %d', $this->amount, $other->amount));
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency->equals($other->currency) && $this->amount === $other->amount;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount >= $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function isLessThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount <= $other->amount;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isZero(): bool
    {
        return 0 === $this->amount;
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }
}
