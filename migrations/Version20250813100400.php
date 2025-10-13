<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add boolean `private` column to groups.
 */
final class Version20250813100400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add groups.private boolean column (default false, not null).';
    }

    public function up(Schema $schema): void
    {
        // Add the `private` flag to groups; default false for existing rows.
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups ADD COLUMN private TINYINT(1) NOT NULL DEFAULT 0 after name
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        // Remove the `private` flag from groups.
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups DROP private
        SQL
        );
    }
}
