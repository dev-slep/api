<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Tests\Application;

use App\Tests\Support\Attribute\CoversContractMethod;
use App\Tests\Support\Policy\Fixtures\Src\Billing\Contract\BillingApi;

#[CoversContractMethod(BillingApi::class, 'charge')]
final class BillingApiCoverage
{
}
