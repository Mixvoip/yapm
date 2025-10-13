<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250911085520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add meta_data to share_processes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE share_processes ADD COLUMN metadata VARCHAR(255) NOT NULL after scope_id
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE share_processes DROP metadata
        SQL
        );
    }
}
