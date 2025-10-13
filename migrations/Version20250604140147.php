<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250604140147 extends AbstractMigration
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
            ALTER TABLE folders DROP FOREIGN KEY FK_FE37D30FE76796AC
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders 
            ADD CONSTRAINT FK_FE37D30FE76796AC FOREIGN KEY (parent_folder_id) REFERENCES folders (id) ON DELETE CASCADE
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords DROP FOREIGN KEY FK_ED822B16162CB942
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords 
            ADD CONSTRAINT FK_ED822B16162CB942 FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE CASCADE
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders DROP FOREIGN KEY FK_FE37D30FE76796AC
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE folders ADD CONSTRAINT FK_FE37D30FE76796AC FOREIGN KEY (parent_folder_id) REFERENCES folders (id)
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords DROP FOREIGN KEY FK_ED822B16162CB942
        SQL
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE passwords ADD CONSTRAINT FK_ED822B16162CB942 FOREIGN KEY (folder_id) REFERENCES folders (id)
        SQL
        );
    }
}
