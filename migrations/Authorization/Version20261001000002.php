<?php

declare(strict_types=1);

namespace Migrations\Authorization;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the authorization schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "authorization"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "authorization"');
    }
}
