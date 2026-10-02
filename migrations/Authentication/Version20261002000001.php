<?php

declare(strict_types=1);

namespace Migrations\Authentication;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Authentication: accounts, social identities, refresh tokens, one-time tokens, two-factor secrets, rate limiter state, processed events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE authentication.user_account (
                id UUID NOT NULL,
                email VARCHAR(254) NOT NULL,
                password_hash VARCHAR(255) DEFAULT NULL,
                role VARCHAR(16) NOT NULL,
                phone VARCHAR(32) DEFAULT NULL,
                locale VARCHAR(16) NOT NULL,
                email_verified_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                status VARCHAR(16) NOT NULL,
                registered_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                password_changed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_696AE541E7927C74 ON authentication.user_account (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE authentication.social_identity (
                provider VARCHAR(16) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                account_id UUID NOT NULL,
                PRIMARY KEY (provider, subject)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_social_identity_account ON authentication.social_identity (account_id)');
        $this->addSql('ALTER TABLE authentication.social_identity ADD CONSTRAINT FK_SOCIAL_IDENTITY_ACCOUNT FOREIGN KEY (account_id) REFERENCES authentication.user_account (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE authentication.refresh_token (
                id UUID NOT NULL,
                family_id UUID NOT NULL,
                account_id UUID NOT NULL,
                hash VARCHAR(64) NOT NULL,
                issued_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                rotated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                replaced_by UUID DEFAULT NULL,
                version INT DEFAULT 1 NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F7B68FD1D1B862B8 ON authentication.refresh_token (hash)');
        $this->addSql('CREATE INDEX idx_refresh_token_family ON authentication.refresh_token (family_id)');
        $this->addSql('CREATE INDEX idx_refresh_token_account ON authentication.refresh_token (account_id)');
        $this->addSql('CREATE INDEX idx_refresh_token_expires ON authentication.refresh_token (expires_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE authentication.one_time_token (
                id UUID NOT NULL,
                account_id UUID NOT NULL,
                purpose VARCHAR(32) NOT NULL,
                hash VARCHAR(64) NOT NULL,
                issued_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                invalidated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_one_time_token_hash ON authentication.one_time_token (purpose, hash)');
        $this->addSql('CREATE INDEX idx_one_time_token_account ON authentication.one_time_token (account_id, purpose)');
        $this->addSql('CREATE INDEX idx_one_time_token_expires ON authentication.one_time_token (expires_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE authentication.two_factor_secret (
                id UUID NOT NULL,
                account_id UUID NOT NULL,
                encrypted_secret TEXT NOT NULL,
                confirmed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                recovery_code_hashes JSON NOT NULL,
                last_used_step BIGINT DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C1F5879F9B6B5FBA ON authentication.two_factor_secret (account_id)');

        // Rate limiter state (see DbalRateLimiterStorage) and the idempotency marker of event subscribers
        $this->addSql(<<<'SQL'
            CREATE TABLE authentication.rate_limit (
                id VARCHAR(255) NOT NULL,
                state TEXT NOT NULL,
                expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_rate_limit_expires ON authentication.rate_limit (expires_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE authentication.processed_event (
                subscriber VARCHAR(255) NOT NULL,
                event_id VARCHAR(255) NOT NULL,
                processed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (subscriber, event_id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE authentication.processed_event');
        $this->addSql('DROP TABLE authentication.rate_limit');
        $this->addSql('DROP TABLE authentication.two_factor_secret');
        $this->addSql('DROP TABLE authentication.one_time_token');
        $this->addSql('DROP TABLE authentication.refresh_token');
        $this->addSql('DROP TABLE authentication.social_identity');
        $this->addSql('DROP TABLE authentication.user_account');
    }
}
