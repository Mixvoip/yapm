<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620065229 extends AbstractMigration
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
            ALTER TABLE folders
                ADD external_id VARCHAR(255) DEFAULT NULL AFTER name
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords
                ADD external_id VARCHAR(255) DEFAULT NULL AFTER title,
                ADD encrypted_username VARCHAR(255) DEFAULT NULL AFTER external_id,
                ADD encrypted_password VARCHAR(255) DEFAULT NULL AFTER encrypted_username,
                ADD username_nonce VARCHAR(255) DEFAULT NULL AFTER encrypted_password,
                ADD password_nonce VARCHAR(255) DEFAULT NULL AFTER username_nonce,
                ADD location VARCHAR(255) DEFAULT NULL AFTER description,
                DROP encrypted_data,
                DROP nonce
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE vaults
                ADD mandatory_password_fields SET('external_id', 'target', 'location') DEFAULT NULL AFTER name,
                ADD mandatory_folder_fields SET('external_id') DEFAULT NULL AFTER mandatory_password_fields
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders 
            DROP external_id
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords
                ADD encrypted_data VARCHAR(255) NOT NULL,
                ADD nonce VARCHAR(255) NOT NULL,
                DROP external_id,
                DROP encrypted_username,
                DROP encrypted_password,
                DROP username_nonce,
                DROP password_nonce,
                DROP location
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE vaults 
                DROP mandatory_password_fields,
                DROP mandatory_folder_fields
        SQL
        );
    }
}
