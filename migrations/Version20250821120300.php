<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add boolean `partial` column to folders_groups and groups_vaults to mark propagated (partial-read) relations.
 */
final class Version20250821120300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add partial boolean columns (default false, not null) to folders_groups and groups_vaults.';
    }

    public function up(Schema $schema): void
    {
        // Add the `partial` flag to relation tables; default false for existing rows.
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups ADD COLUMN partial TINYINT(1) NOT NULL DEFAULT 0 AFTER can_write
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults ADD COLUMN partial TINYINT(1) NOT NULL DEFAULT 0 AFTER can_write
        SQL
        );

        // Disallow partial = 1 together with canWrite = 1 on both tables.
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
    }

    public function down(Schema $schema): void
    {
        // Drop the constraints first, then remove the `partial` flag from relation tables.
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups DROP CHECK chk_folders_groups_partial_cannot_write
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults DROP CHECK chk_groups_vaults_partial_cannot_write
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups DROP partial
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults DROP partial
        SQL
        );
    }
}
