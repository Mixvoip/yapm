<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250528142439 extends AbstractMigration
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
            CREATE TABLE folders (
                id CHAR(36) NOT NULL,
                name VARCHAR(128) CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` NOT NULL,
                vault_id CHAR(36) DEFAULT NULL,
                parent_folder_id CHAR(36) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(255) NOT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(255) DEFAULT NULL,
                INDEX vault_id (vault_id),
                INDEX parent_folder_id (parent_folder_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE TABLE folders_groups (
                group_id CHAR(36) NOT NULL,
                folder_id CHAR(36) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(255) NOT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(255) DEFAULT NULL,
                INDEX group_id (group_id),
                INDEX folder_id (folder_id), 
                PRIMARY KEY(group_id, folder_id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE TABLE groups_passwords (
                group_id CHAR(36) NOT NULL,
                password_id CHAR(36) NOT NULL,
                encrypted_password_key VARCHAR(255) NOT NULL,
                encryption_public_key VARCHAR(255) NOT NULL,
                nonce VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(255) NOT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(255) DEFAULT NULL,
                INDEX group_id (group_id),
                INDEX password_id (password_id),
                PRIMARY KEY(group_id, password_id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE TABLE groups_users (
                group_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                manager TINYINT(1) DEFAULT 0 NOT NULL,
                encrypted_group_private_key VARCHAR(255) NOT NULL,
                group_private_key_nonce VARCHAR(255) NOT NULL,
                encryption_public_key VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(255) NOT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(255) DEFAULT NULL, 
                INDEX group_id (group_id),
                INDEX user_id (user_id),
                PRIMARY KEY(group_id, user_id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE TABLE groups_vaults (
                group_id CHAR(36) NOT NULL,
                vault_id CHAR(36) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(255) NOT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(255) DEFAULT NULL,
                INDEX group_id (group_id),
                INDEX vault_id (vault_id),
                PRIMARY KEY(group_id, vault_id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE TABLE passwords (
                id CHAR(36) NOT NULL,
                title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` NOT NULL,
                encrypted_data VARCHAR(255) NOT NULL,
                nonce VARCHAR(255) NOT NULL,
                target VARCHAR(255) CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` DEFAULT NULL,
                description LONGTEXT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` DEFAULT NULL,
                vault_id CHAR(36) NOT NULL,
                folder_id CHAR(36) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(255) NOT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(255) DEFAULT NULL,
                INDEX vault_id (vault_id),
                INDEX folder_id (folder_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE TABLE vaults (
                id CHAR(36) NOT NULL,
                name VARCHAR(128) CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` NOT NULL,
                user_id CHAR(36) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(255) NOT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(255) DEFAULT NULL,
                INDEX user_id (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders 
            ADD CONSTRAINT FK_FE37D30F58AC2DF8 FOREIGN KEY (vault_id) REFERENCES vaults (id)
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders 
            ADD CONSTRAINT FK_FE37D30FE76796AC FOREIGN KEY (parent_folder_id) REFERENCES folders (id)
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups 
            ADD CONSTRAINT FK_1E04C599FE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups 
            ADD CONSTRAINT FK_1E04C599162CB942 FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_passwords 
            ADD CONSTRAINT FK_FB55F6A9FE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_passwords 
            ADD CONSTRAINT FK_FB55F6A93E4A79C1 FOREIGN KEY (password_id) REFERENCES passwords (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_users 
            ADD CONSTRAINT FK_4520C24DFE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_users 
            ADD CONSTRAINT FK_4520C24DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults 
            ADD CONSTRAINT FK_86722B8AFE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults 
            ADD CONSTRAINT FK_86722B8A58AC2DF8 FOREIGN KEY (vault_id) REFERENCES vaults (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords 
            ADD CONSTRAINT FK_ED822B1658AC2DF8 FOREIGN KEY (vault_id) REFERENCES vaults (id)
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords 
            ADD CONSTRAINT FK_ED822B16162CB942 FOREIGN KEY (folder_id) REFERENCES folders (id)
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE vaults 
            ADD CONSTRAINT FK_5798EF1CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users_groups DROP FOREIGN KEY FK_FF8AB7E0A76ED395
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users_groups DROP FOREIGN KEY FK_FF8AB7E0FE54D947
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE users_groups
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups ADD public_key VARCHAR(255) NOT NULL AFTER name
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE UNIQUE INDEX public_key ON groups (public_key)
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users 
            ADD public_key VARCHAR(255) DEFAULT NULL,
            ADD encrypted_private_key VARCHAR(255) DEFAULT NULL,
            ADD private_key_nonce VARCHAR(255) DEFAULT NULL,
            ADD key_salt VARCHAR(255) DEFAULT NULL
        SQL
        );
        $this->addSql(
            <<<'SQL'
            CREATE UNIQUE INDEX public_key ON users (public_key)
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            CREATE TABLE users_groups (
                group_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
                user_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
                INDEX IDX_FF8AB7E0FE54D947 (group_id),
                INDEX IDX_FF8AB7E0A76ED395 (user_id),
                PRIMARY KEY(group_id, user_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users_groups 
            ADD CONSTRAINT FK_FF8AB7E0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users_groups 
            ADD CONSTRAINT FK_FF8AB7E0FE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders DROP FOREIGN KEY FK_FE37D30F58AC2DF8
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders DROP FOREIGN KEY FK_FE37D30FE76796AC
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups DROP FOREIGN KEY FK_1E04C599FE54D947
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders_groups DROP FOREIGN KEY FK_1E04C599162CB942
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_passwords DROP FOREIGN KEY FK_FB55F6A9FE54D947
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_passwords DROP FOREIGN KEY FK_FB55F6A93E4A79C1
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_users DROP FOREIGN KEY FK_4520C24DFE54D947
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_users DROP FOREIGN KEY FK_4520C24DA76ED395
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults DROP FOREIGN KEY FK_86722B8AFE54D947
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups_vaults DROP FOREIGN KEY FK_86722B8A58AC2DF8
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords DROP FOREIGN KEY FK_ED822B1658AC2DF8
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords DROP FOREIGN KEY FK_ED822B16162CB942
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE vaults DROP FOREIGN KEY FK_5798EF1CA76ED395
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE folders
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE folders_groups
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE groups_passwords
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE groups_users
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE groups_vaults
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE passwords
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE vaults
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP INDEX public_key ON groups
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE groups DROP public_key
        SQL
        );
        $this->addSql(
            <<<'SQL'
            DROP INDEX public_key ON users
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users 
            DROP public_key, 
            DROP encrypted_private_key, 
            DROP private_key_nonce, 
            DROP key_salt
        SQL
        );
    }
}
