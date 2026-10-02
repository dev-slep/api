<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Security;

use App\Authentication\Application\Port\AuthenticatedPrincipal;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user of a request, built from the claims of a verified access token (nothing is loaded from the database).
 */
final readonly class AuthenticatedUser implements UserInterface
{
    public function __construct(public AuthenticatedPrincipal $principal)
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->principal->accountId;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->principal->roles;
    }

    public function eraseCredentials(): void
    {
    }
}
