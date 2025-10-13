<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250618061612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups ADD can_write TINYINT(1) DEFAULT 0 NOT NULL
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_passwords ADD can_write TINYINT(1) DEFAULT 0 NOT NULL
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults ADD can_write TINYINT(1) DEFAULT 0 NOT NULL
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups DROP can_write
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults DROP can_write
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_passwords DROP can_write
        SQL
        );
    }
}
