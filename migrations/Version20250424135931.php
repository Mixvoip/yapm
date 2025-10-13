<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250424135931 extends AbstractMigration
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
            CREATE TABLE refresh_tokens (
                refresh_token VARCHAR(128) NOT NULL,
                username VARCHAR(255) NOT NULL,
                valid DATETIME DEFAULT NULL,
                id INT AUTO_INCREMENT NOT NULL,
                UNIQUE INDEX refresh_token (refresh_token),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            DROP TABLE refresh_tokens
        SQL
        );
    }
}
