<?php

declare(strict_types=1);

namespace Migrations\Notification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the notification schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "notification"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "notification"');
    }
}
