<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * For integration tests that commit for real (e.g. they consume messages in a worker):
 * mark the class with `#[SkipDatabaseRollback]` and call {@see self::truncateSchemas()} in setUp.
 */
trait TruncatesSchemas
{
    protected function truncateSchemas(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        /** @var list<string> $schemas */
        $schemas = static::getContainer()->getParameter('app.database_schemas');

        /** @var list<string> $tables */
        $tables = $connection->fetchFirstColumn(
            'SELECT quote_ident(schemaname) || \'.\' || quote_ident(tablename) FROM pg_tables WHERE schemaname IN (?)',
            [$schemas],
            [ArrayParameterType::STRING],
        );

        if ([] !== $tables) {
            $connection->executeStatement('TRUNCATE TABLE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');
        }
    }
}
