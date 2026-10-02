<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use App\Authentication\Domain\Exception\AuthenticationProblem;

interface AccessTokenVerifier
{
    /**
     * @throws AuthenticationProblem token-expired or token-invalid
     */
    public function verify(string $token): AuthenticatedPrincipal;
}
