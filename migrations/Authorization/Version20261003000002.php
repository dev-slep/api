<?php

declare(strict_types=1);

namespace Migrations\Authorization;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Authorization: role assignments (one per user) and processed events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "authorization".role_assignment (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                role VARCHAR(16) NOT NULL,
                granted_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_role_assignment_user ON "authorization".role_assignment (user_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE "authorization".processed_event (
                subscriber VARCHAR(255) NOT NULL,
                event_id VARCHAR(255) NOT NULL,
                processed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (subscriber, event_id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "authorization".processed_event');
        $this->addSql('DROP TABLE "authorization".role_assignment');
    }
}
