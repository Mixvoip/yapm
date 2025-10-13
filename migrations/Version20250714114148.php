<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250714114148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'create audit logs and messenger tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            CREATE TABLE audit_logs (
                id CHAR(36) NOT NULL,
                action_type VARCHAR(255) NOT NULL,
                entity_type VARCHAR(255) NOT NULL,
                entity_id VARCHAR(255) NOT NULL,
                user_id CHAR(36) DEFAULT NULL,
                user_email VARCHAR(180) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent LONGTEXT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` DEFAULT NULL,
                old_values JSON DEFAULT NULL,
                new_values JSON DEFAULT NULL,
                metadata LONGTEXT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX entity_type (entity_type),
                INDEX entity_id (entity_id),
                INDEX action_type (action_type),
                INDEX user_id (user_id),
                INDEX user_email (user_email),
                INDEX created_at (created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET ascii COLLATE `ascii_general_ci`
        SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE `messenger_messages` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `body` longtext NOT NULL,
                `headers` longtext NOT NULL,
                `queue_name` varchar(190) NOT NULL,
                `created_at` datetime NOT NULL,
                `available_at` datetime NOT NULL,
                `delivered_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `IDX_75EA56E016BA31DB` (`delivered_at`),
                KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
                KEY `IDX_75EA56E0FB7336F0` (`queue_name`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            DROP TABLE audit_logs
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP TABLE messenger_messages
        SQL
        );
    }
}
