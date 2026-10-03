<?php

declare(strict_types=1);

namespace Migrations\Audit;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit: the append-only audit_entry table (a trigger blocks UPDATE and DELETE) and processed events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE audit.audit_entry (
                id UUID NOT NULL,
                occurred_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                kind VARCHAR(16) NOT NULL,
                name VARCHAR(160) NOT NULL,
                actor_type VARCHAR(16) NOT NULL,
                actor_id UUID DEFAULT NULL,
                actor_role VARCHAR(32) DEFAULT NULL,
                target_id VARCHAR(64) DEFAULT NULL,
                payload JSONB NOT NULL,
                outcome VARCHAR(16) NOT NULL,
                failure_reason VARCHAR(500) DEFAULT NULL,
                correlation_id VARCHAR(64) DEFAULT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_audit_entry_occurred_at ON audit.audit_entry (occurred_at DESC)');
        $this->addSql('CREATE INDEX idx_audit_entry_actor ON audit.audit_entry (actor_id, occurred_at DESC)');
        $this->addSql('CREATE INDEX idx_audit_entry_target ON audit.audit_entry (target_id, occurred_at DESC)');
        $this->addSql('CREATE INDEX idx_audit_entry_name ON audit.audit_entry (name, occurred_at DESC)');

        $this->addSql(<<<'SQL'
            CREATE FUNCTION audit.reject_audit_entry_change() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit.audit_entry is append-only (% is not allowed)', TG_OP;
            END;
            $$
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TRIGGER audit_entry_append_only
                BEFORE UPDATE OR DELETE ON audit.audit_entry
                FOR EACH ROW EXECUTE FUNCTION audit.reject_audit_entry_change()
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE audit.processed_event (
                subscriber VARCHAR(255) NOT NULL,
                event_id VARCHAR(255) NOT NULL,
                processed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (subscriber, event_id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit.processed_event');
        $this->addSql('DROP TRIGGER audit_entry_append_only ON audit.audit_entry');
        $this->addSql('DROP FUNCTION audit.reject_audit_entry_change()');
        $this->addSql('DROP TABLE audit.audit_entry');
    }
}
