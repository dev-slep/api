<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Id;

use App\SharedKernel\Domain\IdGenerator;
use Symfony\Component\Uid\Uuid;

final readonly class UuidV7IdGenerator implements IdGenerator
{
    public function generate(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
