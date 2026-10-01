<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FixturePayload
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name = '',
        #[Assert\Range(min: 1, max: 10)]
        public int $count = 0,
    ) {
    }
}
