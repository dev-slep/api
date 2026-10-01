<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Src\Billing\Domain\Model;

abstract class Shape
{
    public function area(): int
    {
        return 0;
    }
}
