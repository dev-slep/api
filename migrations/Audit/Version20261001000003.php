<?php

declare(strict_types=1);

namespace Migrations\Audit;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the audit schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "audit"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "audit"');
    }
}
