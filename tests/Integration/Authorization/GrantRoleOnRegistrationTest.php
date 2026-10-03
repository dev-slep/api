<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authorization;

use App\Authorization\Infrastructure\Messaging\GrantRoleOnRegistrationSubscriber;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use App\Tests\Support\TruncatesSchemas;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The registration event travels through the real transport and is handled by the worker, which commits for real.
 */
#[CoversClass(GrantRoleOnRegistrationSubscriber::class)]
#[SkipDatabaseRollback]
final class GrantRoleOnRegistrationTest extends AuthenticationIntegrationTestCase
{
    use TruncatesSchemas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->truncateSchemas();
    }

    protected function tearDown(): void
    {
        $this->truncateSchemas();
        parent::tearDown();
    }

    public function testRegisteringGrantsTheRoleAndTheGrantIsAudited(): void
    {
        $email = $this->uniqueEmail('tow');
        $response = $this->call('POST', '/api/v1/auth/register', ['email' => $email, 'password' => self::PASSWORD, 'role' => 'TOWER', 'locale' => 'en']);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        $output = $this->consumeMessages('async', 6);

        $row = $this->connection()->fetchAssociative('SELECT user_id, role FROM "authorization".role_assignment');
        self::assertIsArray($row, $output);
        self::assertSame('TOWER', $row['role']);
        $userId = $row['user_id'];
        self::assertIsString($userId);
        self::assertSame(1, $this->countRows('"authorization".processed_event'));
        self::assertSame(1, $this->countRows('audit.audit_entry', "name = 'authorization.role_granted.v1' AND target_id = '".$userId."'"), $output);
        self::assertSame(0, $this->countRows('messenger.messenger_messages', "queue_name = 'failed'"));
    }
}
