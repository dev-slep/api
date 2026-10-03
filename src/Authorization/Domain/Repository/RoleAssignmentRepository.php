<?php

declare(strict_types=1);

namespace App\Authorization\Domain\Repository;

use App\Authorization\Domain\Model\AssignedUserId;
use App\Authorization\Domain\Model\RoleAssignment;

interface RoleAssignmentRepository
{
    public function findByUserId(AssignedUserId $userId): ?RoleAssignment;

    public function save(RoleAssignment $assignment): void;
}
