<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication\Repository;

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * For the Doctrine repository tests, which extend a repository contract (a plain test case) and need the real
 * entity manager: boots the test kernel (the DAMA extension rolls the database back after every test).
 */
trait UsesDoctrine
{
    private ?Kernel $doctrineKernel = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->doctrineKernel?->shutdown();
        $this->doctrineKernel = null;
    }

    protected function entityManager(): EntityManagerInterface
    {
        if (null === $this->doctrineKernel) {
            $this->doctrineKernel = new Kernel('test', true);
            $this->doctrineKernel->boot();
        }

        $manager = $this->doctrineKernel->getContainer()->get('doctrine.orm.entity_manager');

        return $manager;
    }

    /**
     * Forgets everything the entity manager loaded, so the next read comes from the database.
     */
    protected function forget(): void
    {
        $this->entityManager()->clear();
    }
}
