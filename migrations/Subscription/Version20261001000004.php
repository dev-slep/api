<?php

declare(strict_types=1);

namespace Migrations\Subscription;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the subscription schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "subscription"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "subscription"');
    }
}
