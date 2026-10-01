<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Tests\Unit;

use App\Tests\Support\Policy\Fixtures\Src\Billing\Domain\Model\Ledger;
use PHPUnit\Framework\Attributes\CoversMethod;

#[CoversMethod(Ledger::class, 'record')]
final class LedgerCoverage
{
}
