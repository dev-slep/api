<?php

declare(strict_types=1);

namespace Migrations\Driver;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the driver schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "driver"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "driver"');
    }
}
