<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260129000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "add TOTP support fields to passwords table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `passwords`
             ADD COLUMN encrypted_totp_secret_key VARCHAR(255) DEFAULT NULL after password_nonce,
             ADD COLUMN totp_secret_key_nonce VARCHAR(255) DEFAULT NULL after encrypted_totp_secret_key,
             ADD COLUMN totp_algorithm ENUM('sha1', 'sha256', 'sha512') DEFAULT NULL after totp_secret_key_nonce,
             ADD COLUMN totp_period SMALLINT DEFAULT NULL after totp_algorithm,
             ADD COLUMN totp_digits SMALLINT DEFAULT NULL after totp_period
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `passwords`
             DROP encrypted_totp_secret_key,
             DROP totp_secret_key_nonce,
             DROP totp_algorithm,
             DROP totp_period,
             DROP totp_digits
        SQL
        );
    }
}
