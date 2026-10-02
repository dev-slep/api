<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Security;

use App\Authentication\Contract\CurrentUser;
use App\SharedKernel\Contract\UserId;
use LogicException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The {@see CurrentUser} contract, answered from the security token of the current request.
 */
final readonly class SecurityCurrentUser implements CurrentUser
{
    public function __construct(private TokenStorageInterface $tokens)
    {
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->user();
    }

    public function id(): UserId
    {
        $user = $this->user() ?? throw new LogicException('Nobody is authenticated.');

        return new UserId($user->principal->accountId);
    }

    public function roles(): array
    {
        return $this->user()?->getRoles() ?? [];
    }

    public function isTwoFactorVerified(): bool
    {
        return $this->user()?->principal->isTwoFactorVerified() ?? false;
    }

    private function user(): ?AuthenticatedUser
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof AuthenticatedUser ? $user : null;
    }
}
