<?php

declare(strict_types=1);

namespace Migrations\Authentication;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the authentication schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "authentication"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "authentication"');
    }
}
