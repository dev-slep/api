<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Src\Billing\Application\Command;

use App\Tests\Support\Policy\Fixtures\FixtureHandler;

final class PayInvoiceHandler implements FixtureHandler
{
    public function __invoke(PayInvoice $command): void
    {
    }
}
