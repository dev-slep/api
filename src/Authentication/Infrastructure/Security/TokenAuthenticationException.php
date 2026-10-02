<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Security;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Carries the specific problem (token-expired, token-invalid) of a rejected access token to the failure handler.
 */
final class TokenAuthenticationException extends AuthenticationException
{
    public function __construct(public readonly AuthenticationProblem $problem)
    {
        parent::__construct($problem->getMessage(), 0, $problem);
    }
}
