<?php

declare(strict_types=1);

namespace Migrations\TowRequest;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the tow_request schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "tow_request"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "tow_request"');
    }
}
