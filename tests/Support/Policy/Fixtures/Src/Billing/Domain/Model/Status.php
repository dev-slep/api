<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Src\Billing\Domain\Model;

enum Status
{
    case Open;
    case Paid;

    public function label(): string
    {
        return $this->name;
    }
}
