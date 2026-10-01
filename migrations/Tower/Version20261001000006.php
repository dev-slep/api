<?php

declare(strict_types=1);

namespace Migrations\Tower;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the tower schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "tower"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "tower"');
    }
}
