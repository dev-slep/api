<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Src\Billing\Infrastructure\Http\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class BrokenController
{
    #[Route('/api/v1/broken')]
    public function broken(): void
    {
    }
}
