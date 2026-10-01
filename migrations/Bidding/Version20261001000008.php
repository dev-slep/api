<?php

declare(strict_types=1);

namespace Migrations\Bidding;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the bidding schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "bidding"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "bidding"');
    }
}
