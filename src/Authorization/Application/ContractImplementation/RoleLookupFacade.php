<?php

declare(strict_types=1);

namespace App\Authorization\Application\ContractImplementation;

use App\Authorization\Contract\RoleLookup;
use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Repository\RoleAssignmentRepository;
use App\SharedKernel\Contract\UserId;

final readonly class RoleLookupFacade implements RoleLookup
{
    public function __construct(private RoleAssignmentRepository $assignments)
    {
    }

    public function rolesFor(UserId $userId): array
    {
        $assignment = $this->assignments->findByUserId(new AssignedUserId($userId->toString()));

        return null === $assignment ? [] : [$assignment->role()->securityRole()];
    }
}
