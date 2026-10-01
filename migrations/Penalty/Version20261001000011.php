<?php

declare(strict_types=1);

namespace Migrations\Penalty;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the penalty schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "penalty"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "penalty"');
    }
}
