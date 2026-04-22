<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "create webauthn_credentials table for passkey support with PRF encryption";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE TABLE webauthn_credentials (
                id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                credential_id VARBINARY(1024) NOT NULL,
                public_key_credential_source TEXT NOT NULL,
                name VARCHAR(255) NOT NULL,
                prf_salt VARCHAR(255) NOT NULL,
                prf_encrypted_private_key VARCHAR(255) NOT NULL,
                prf_private_key_nonce VARCHAR(255) NOT NULL,
                cache_on_use TINYINT(1) NOT NULL DEFAULT 1,
                last_used_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_by VARCHAR(255) NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)',
                updated_by VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX webauthn_credential_id (credential_id),
                INDEX webauthn_user (user_id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci` ENGINE = InnoDB
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE webauthn_credentials
            ADD CONSTRAINT FK_webauthn_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webauthn_credentials DROP FOREIGN KEY FK_webauthn_user');
        $this->addSql('DROP TABLE webauthn_credentials');
    }
}
