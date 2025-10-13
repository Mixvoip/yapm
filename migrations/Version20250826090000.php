<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create share_processes and share_items tables aligned with entities; drop previous CHECK constraints on `partial` columns.
 */
final class Version20250826090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create share_processes/share_items tables aligned with entities and drop partial write CHECK constraints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE TABLE share_processes (
                id CHAR(36) NOT NULL,
                scope_id CHAR(36) NOT NULL,
                target_type VARCHAR(20) NOT NULL,
                is_cascade TINYINT(1) NOT NULL DEFAULT 0,
                requested_groups JSON NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                total_items INT DEFAULT NULL,
                processed_items INT NOT NULL DEFAULT 0,
                failed_items INT NOT NULL DEFAULT 0,
                message LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(180) NOT NULL,
                started_at DATETIME DEFAULT NULL,
                finished_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                INDEX created_at (created_at),
                INDEX started_at (started_at),
                INDEX finished_at (finished_at),
                INDEX scope_id (scope_id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci` ENGINE = InnoDB
        SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE share_items (
                id CHAR(36) NOT NULL,
                process_id CHAR(36) NOT NULL,
                target_type VARCHAR(20) NOT NULL,
                target_id CHAR(36) NOT NULL,
                status VARCHAR(20) NOT NULL,
                message LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX process_id (process_id),
                INDEX target_type (target_type),
                INDEX target_id (target_id),
                INDEX status (status),
                INDEX created_at (created_at),
                INDEX processed_at (processed_at),
                UNIQUE KEY uniq_process_target (process_id, target_type, target_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_SHARE_ITEM_PROCESS FOREIGN KEY (process_id) REFERENCES share_processes (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci` ENGINE = InnoDB
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups DROP CONSTRAINT chk_folders_groups_partial_cannot_write
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults DROP CONSTRAINT chk_groups_vaults_partial_cannot_write
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups
                ADD CONSTRAINT chk_folders_groups_partial_cannot_write
                CHECK (NOT (partial = 1 AND can_write = 1))
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults
                ADD CONSTRAINT chk_groups_vaults_partial_cannot_write
                CHECK (NOT (partial = 1 AND can_write = 1))
        SQL
        );

        $this->addSql('ALTER TABLE share_items DROP FOREIGN KEY FK_SHARE_ITEM_PROCESS');
        $this->addSql('DROP TABLE share_items');
        $this->addSql('DROP TABLE share_processes');
    }
}
