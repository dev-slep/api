<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Persistence;

use App\Authentication\Infrastructure\Persistence\DoctrineTwoFactorSecretRepository;
use App\Authentication\Infrastructure\Persistence\Mapper\TwoFactorSecretMapper;
use App\Tests\Support\Authentication\Repository\TwoFactorSecretRepositoryContract;
use App\Tests\Support\Authentication\Repository\UsesDoctrine;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(DoctrineTwoFactorSecretRepository::class)]
final class DoctrineTwoFactorSecretRepositoryTest extends TwoFactorSecretRepositoryContract
{
    use UsesDoctrine;

    protected function createRepository(): object
    {
        return new DoctrineTwoFactorSecretRepository($this->entityManager(), new TwoFactorSecretMapper(), $this->collector);
    }

    public function testThereIsOneSecretPerAccountInTheDatabase(): void
    {
        $this->repository->save($this->secret(1));
        $this->forget();

        $this->expectException(Throwable::class);
        $this->repository->save($this->secret(2));
    }
}
