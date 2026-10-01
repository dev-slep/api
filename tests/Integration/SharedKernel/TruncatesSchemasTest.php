<?php

declare(strict_types=1);

namespace App\Tests\Integration\SharedKernel;

use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\TruncatesSchemas;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
#[SkipDatabaseRollback]
final class TruncatesSchemasTest extends IntegrationTestCase
{
    use TruncatesSchemas;

    public function testTruncateEmptiesTablesInModuleSchemas(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        $connection->executeStatement('CREATE TABLE tow_request.truncate_probe (id INT)');

        try {
            $connection->executeStatement('INSERT INTO tow_request.truncate_probe VALUES (1), (2)');
            self::assertEquals(2, $connection->fetchOne('SELECT COUNT(*) FROM tow_request.truncate_probe'));

            $this->truncateSchemas();

            self::assertEquals(0, $connection->fetchOne('SELECT COUNT(*) FROM tow_request.truncate_probe'));
        } finally {
            $connection->executeStatement('DROP TABLE tow_request.truncate_probe');
        }
    }
}
