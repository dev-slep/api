<?php

declare(strict_types=1);

namespace Migrations\Job;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the job schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "job"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "job"');
    }
}
