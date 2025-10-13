<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250423141950 extends AbstractMigration
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
            CREATE TABLE `groups` (
                id CHAR(36) NOT NULL,
                name VARCHAR(180) CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX name (name),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE TABLE users_groups (
                group_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                INDEX IDX_FF8AB7E0FE54D947 (group_id),
                INDEX IDX_FF8AB7E0A76ED395 (user_id),
                PRIMARY KEY(group_id, user_id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE TABLE `users` (
                id CHAR(36) NOT NULL,
                email VARCHAR(180) NOT NULL,
                username VARCHAR(180) NOT NULL,
                admin TINYINT(1) NOT NULL DEFAULT 0,
                password VARCHAR(255) DEFAULT NULL,
                verified TINYINT(1) NOT NULL DEFAULT 0,
                verification_token VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX email (email),
                UNIQUE INDEX username (username),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users_groups 
            ADD CONSTRAINT FK_FF8AB7E0FE54D947 FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users_groups 
            ADD CONSTRAINT FK_FF8AB7E0A76ED395 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users_groups DROP FOREIGN KEY FK_FF8AB7E0FE54D947
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users_groups DROP FOREIGN KEY FK_FF8AB7E0A76ED395
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE `groups`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE users_groups
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE `users`
        SQL
        );
    }
}
