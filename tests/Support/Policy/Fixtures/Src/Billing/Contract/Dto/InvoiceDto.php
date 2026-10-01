<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Src\Billing\Contract\Dto;

final class InvoiceDto
{
    public function amount(): int
    {
        return 1;
    }
}
