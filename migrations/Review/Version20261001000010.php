<?php

declare(strict_types=1);

namespace Migrations\Review;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the review schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS "review"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA "review"');
    }
}
