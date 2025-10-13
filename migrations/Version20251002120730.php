<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251002120730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "add deleted_at, deleted_by to vaults, groups, passwords and folders";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `groups`
             ADD COLUMN deleted_at DATETIME DEFAULT NULL,
             ADD COLUMN deleted_by VARCHAR(255) DEFAULT NULL,
             ADD INDEX deleted_at (deleted_at)
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `folders`
             ADD COLUMN deleted_at DATETIME DEFAULT NULL,
             ADD COLUMN deleted_by VARCHAR(255) DEFAULT NULL,
             ADD INDEX deleted_at (deleted_at)
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `passwords`
             ADD COLUMN deleted_at DATETIME DEFAULT NULL,
             ADD COLUMN deleted_by VARCHAR(255) DEFAULT NULL,
             ADD INDEX deleted_at (deleted_at)
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `vaults`
             ADD COLUMN deleted_at DATETIME DEFAULT NULL,
             ADD COLUMN deleted_by VARCHAR(255) DEFAULT NULL,
             ADD INDEX deleted_at (deleted_at)
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `vaults` DROP deleted_at, DROP deleted_by, DROP INDEX deleted_at
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `passwords` DROP deleted_at, DROP deleted_by, DROP INDEX deleted_at
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `groups` DROP deleted_at, DROP deleted_by, DROP INDEX deleted_at
        SQL
        );

        $this->addSql(
            <<<'SQL'
            ALTER TABLE `folders` DROP deleted_at, DROP deleted_by, DROP INDEX deleted_at
        SQL
        );
    }
}
