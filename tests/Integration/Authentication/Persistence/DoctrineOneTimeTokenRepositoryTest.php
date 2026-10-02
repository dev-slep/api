<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Persistence;

use App\Authentication\Infrastructure\Persistence\DoctrineOneTimeTokenRepository;
use App\Authentication\Infrastructure\Persistence\Mapper\OneTimeTokenMapper;
use App\Tests\Support\Authentication\Repository\OneTimeTokenRepositoryContract;
use App\Tests\Support\Authentication\Repository\UsesDoctrine;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DoctrineOneTimeTokenRepository::class)]
final class DoctrineOneTimeTokenRepositoryTest extends OneTimeTokenRepositoryContract
{
    use UsesDoctrine;

    protected function createRepository(): object
    {
        return new DoctrineOneTimeTokenRepository($this->entityManager(), new OneTimeTokenMapper());
    }
}
