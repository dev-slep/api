<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The firewall is stateless and the authenticator builds the user from the token, so there is nothing to load or
 * refresh: asking this provider for a user always fails.
 *
 * @implements UserProviderInterface<AuthenticatedUser>
 */
final readonly class JwtUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('Users are only built from access tokens.');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException('Users are not refreshed: the firewall is stateless.');
    }

    public function supportsClass(string $class): bool
    {
        return AuthenticatedUser::class === $class;
    }
}
