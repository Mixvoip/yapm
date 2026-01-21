<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251224094302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "add requested_users column to share_processes";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `share_processes`
             ADD COLUMN requested_users JSON DEFAULT NULL after requested_groups
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `share_processes` DROP requested_users
        SQL
        );
    }
}
