<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Src\Billing\Infrastructure\Http\Controller;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/invoices')]
final class InvoiceController
{
    #[Route('', methods: ['GET'])]
    public function list(): void
    {
    }

    #[Route('/{id}/pay', methods: ['POST', 'PUT'])]
    public function pay(): void
    {
    }
}
