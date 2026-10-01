<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Src\Billing\Contract;

interface BillingApi
{
    public function charge(string $invoiceId): void;

    public function refund(string $invoiceId): void;
}
