<?php

declare(strict_types=1);

namespace Migrations\Messenger;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the messenger schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "messenger"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "messenger"');
    }
}
