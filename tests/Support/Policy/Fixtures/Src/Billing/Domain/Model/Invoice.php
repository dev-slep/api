<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Src\Billing\Domain\Model;

final class Invoice
{
    public function total(): int
    {
        $this->hidden();

        return 1;
    }

    public function pay(): void
    {
    }

    private function hidden(): void
    {
    }
}
