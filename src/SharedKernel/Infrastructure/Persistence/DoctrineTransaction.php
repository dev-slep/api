<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Persistence;

use App\SharedKernel\Application\Transaction;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTransaction implements Transaction
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function run(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction($work);
    }
}
