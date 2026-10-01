<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Tests\Unit;

use App\Tests\Support\Policy\Fixtures\Src\Billing\Domain\Model\Invoice;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Invoice::class)]
final class InvoiceCoverage
{
}
