<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "create time_based_shares table for temporary password sharing";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE TABLE time_based_shares (
                id CHAR(36) NOT NULL,
                shared_by_id CHAR(36) NOT NULL,
                shared_with_id CHAR(36) NOT NULL,
                password_title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` NOT NULL,
                source_password_id CHAR(36) NOT NULL,
                encrypted_password TEXT NOT NULL,
                password_nonce VARCHAR(255) NOT NULL,
                password_encryption_public_key VARCHAR(255) NOT NULL,
                encrypted_username TEXT DEFAULT NULL,
                username_nonce VARCHAR(255) DEFAULT NULL,
                username_encryption_public_key VARCHAR(255) DEFAULT NULL,
                expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                accessed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                INDEX shared_with_expires (shared_with_id, expires_at),
                INDEX shared_by (shared_by_id),
                INDEX expires_at (expires_at),
                INDEX source_password (source_password_id, expires_at)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci` ENGINE = InnoDB
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE time_based_shares
            ADD CONSTRAINT FK_tbs_shared_by FOREIGN KEY (shared_by_id) REFERENCES users (id)
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE time_based_shares
            ADD CONSTRAINT FK_tbs_shared_with FOREIGN KEY (shared_with_id) REFERENCES users (id)
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE time_based_shares DROP FOREIGN KEY FK_tbs_shared_by');
        $this->addSql('ALTER TABLE time_based_shares DROP FOREIGN KEY FK_tbs_shared_with');
        $this->addSql('DROP TABLE time_based_shares');
    }
}
