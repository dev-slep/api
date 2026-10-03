<?php

declare(strict_types=1);

namespace App\Authorization\Infrastructure\Security;

use App\Authorization\Contract\AccessDecider;
use App\Authorization\Contract\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Lets controllers ask for a permission with `#[IsGranted(Permission::ReadAuditLog->value)]`. The decision belongs to
 * {@see AccessDecider}; this class only passes it the roles of the access token, so a token without roles (an admin
 * who has not finished the second factor) is always refused.
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    public function __construct(private readonly AccessDecider $decider)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== Permission::tryFrom($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $permission = Permission::tryFrom($attribute);

        return null !== $permission && $this->decider->allows(array_values($token->getRoleNames()), $permission);
    }
}
