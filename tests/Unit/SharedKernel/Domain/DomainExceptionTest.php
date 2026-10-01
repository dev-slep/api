<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Domain;

use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\CurrencyMismatch;
use App\SharedKernel\Domain\DomainException;
use App\SharedKernel\Domain\InvalidCurrency;
use App\SharedKernel\Domain\InvalidIdentifier;
use App\SharedKernel\Domain\MoneyOverflow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainException::class)]
#[CoversClass(InvalidIdentifier::class)]
#[CoversClass(InvalidCurrency::class)]
#[CoversClass(CurrencyMismatch::class)]
#[CoversClass(MoneyOverflow::class)]
final class DomainExceptionTest extends TestCase
{
    public function testAllSharedKernelExceptionsExtendTheDomainExceptionBase(): void
    {
        $exceptions = [
            InvalidIdentifier::notAUuid('x'),
            InvalidCurrency::code('x'),
            CurrencyMismatch::between(new Currency('RSD'), new Currency('EUR')),
            MoneyOverflow::whileCalculating('x'),
        ];

        foreach ($exceptions as $exception) {
            self::assertContains(DomainException::class, class_parents($exception));
            self::assertContains(\DomainException::class, class_parents($exception));
        }
    }

    public function testNamedConstructorsDescribeTheProblem(): void
    {
        self::assertStringContainsString('"x"', InvalidIdentifier::notAUuid('x')->getMessage());
        self::assertStringContainsString('"x"', InvalidCurrency::code('x')->getMessage());
        self::assertStringContainsString('1 + 2', MoneyOverflow::whileCalculating('1 + 2')->getMessage());
    }
}
