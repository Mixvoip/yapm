<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251017143800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "add description and iconName to folders and vaults";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `folders`
             ADD COLUMN icon_name VARCHAR(255) NOT NULL DEFAULT 'folder' after external_id,
             ADD COLUMN description LONGTEXT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` DEFAULT NULL after icon_name
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `vaults`
            ADD COLUMN description LONGTEXT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` DEFAULT NULL after icon_name
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `passwords`
                CHANGE encrypted_password encrypted_password TEXT DEFAULT NULL
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `passwords`
                CHANGE encrypted_password encrypted_password VARCHAR(255) DEFAULT NULL
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `vaults` DROP description
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `folders` DROP description, DROP icon_name
        SQL
        );
    }
}
