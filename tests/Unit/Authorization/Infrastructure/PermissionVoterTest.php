<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authorization\Infrastructure;

use App\Authorization\Application\ContractImplementation\AccessDeciderFacade;
use App\Authorization\Application\ContractImplementation\RoleLookupFacade;
use App\Authorization\Application\Service\PermissionCatalogue;
use App\Authorization\Contract\Permission;
use App\Authorization\Infrastructure\Persistence\InMemoryRoleAssignmentRepository;
use App\Authorization\Infrastructure\Security\PermissionVoter;
use App\SharedKernel\Infrastructure\Messaging\CollectedAggregateEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(PermissionVoter::class)]
final class PermissionVoterTest extends TestCase
{
    private function voter(): PermissionVoter
    {
        $lookup = new RoleLookupFacade(new InMemoryRoleAssignmentRepository(new CollectedAggregateEvents()));

        return new PermissionVoter(new AccessDeciderFacade($lookup, new PermissionCatalogue()));
    }

    /**
     * @param list<string> $roles
     */
    private function token(array $roles): TokenInterface
    {
        return new UsernamePasswordToken(new InMemoryUser('user', null, $roles), 'main', $roles);
    }

    public function testAnAdminTokenIsGrantedThePermission(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter()->vote($this->token(['ROLE_ADMIN']), null, [Permission::ReadAuditLog->value]));
    }

    public function testOtherRolesAreDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($this->token(['ROLE_DRIVER']), null, [Permission::ReadAuditLog->value]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($this->token(['ROLE_TOWER']), null, [Permission::ReadAuditLog->value]));
    }

    public function testATokenWithoutRolesIsDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($this->token([]), null, [Permission::ReadAuditLog->value]));
    }

    public function testAnonymousIsDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote(new NullToken(), null, [Permission::ReadAuditLog->value]));
    }

    public function testOtherAttributesAreNotItsBusiness(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter()->vote($this->token(['ROLE_ADMIN']), null, ['ROLE_ADMIN']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter()->vote($this->token(['ROLE_ADMIN']), null, ['audit.unknown']));
    }
}
