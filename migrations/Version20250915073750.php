<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250915073750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "add iconName and allowPasswordsAtRoot columns to vaults";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE vaults
             ADD COLUMN icon_name VARCHAR(255) NOT NULL after mandatory_folder_fields,
             ADD COLUMN allow_passwords_at_root TINYINT(1) DEFAULT 1 NOT NULL after icon_name
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE vaults DROP icon_name, DROP allow_passwords_at_root
        SQL
        );
    }
}
