<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use App\SharedKernel\Infrastructure\Http\MoneyNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

#[CoversClass(MoneyNormalizer::class)]
final class MoneyNormalizerTest extends TestCase
{
    public function testNormalizesToAmountAndCurrency(): void
    {
        self::assertSame(['amount' => 150000, 'currency' => 'RSD'], new MoneyNormalizer()->normalize(new Money(150000, new Currency('RSD'))));
    }

    public function testNegativeAndZeroAmountsAreKept(): void
    {
        self::assertSame(['amount' => 0, 'currency' => 'EUR'], new MoneyNormalizer()->normalize(new Money(0, new Currency('EUR'))));
        self::assertSame(['amount' => -5, 'currency' => 'EUR'], new MoneyNormalizer()->normalize(new Money(-5, new Currency('EUR'))));
    }

    public function testDenormalizesFromAmountAndCurrency(): void
    {
        $money = new MoneyNormalizer()->denormalize(['amount' => 1500, 'currency' => 'RSD'], Money::class);

        self::assertTrue($money->equals(new Money(1500, new Currency('RSD'))));
    }

    #[DataProvider('invalidInput')]
    public function testDenormalizationRejectsInvalidInput(mixed $data): void
    {
        $this->expectException(NotNormalizableValueException::class);

        new MoneyNormalizer()->denormalize($data, Money::class);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidInput(): iterable
    {
        yield 'not an array' => ['1500 RSD'];
        yield 'missing currency' => [['amount' => 1]];
        yield 'missing amount' => [['currency' => 'RSD']];
        yield 'float amount' => [['amount' => 1.5, 'currency' => 'RSD']];
        yield 'string amount' => [['amount' => '15', 'currency' => 'RSD']];
        yield 'invalid currency' => [['amount' => 1, 'currency' => 'rsd']];
        yield 'null' => [null];
    }

    public function testSupportsOnlyMoney(): void
    {
        $normalizer = new MoneyNormalizer();

        self::assertTrue($normalizer->supportsNormalization(new Money(1, new Currency('RSD'))));
        self::assertFalse($normalizer->supportsNormalization(new stdClass()));
        self::assertTrue($normalizer->supportsDenormalization([], Money::class));
        self::assertFalse($normalizer->supportsDenormalization([], stdClass::class));
        self::assertSame([Money::class => true], $normalizer->getSupportedTypes(null));
    }
}
