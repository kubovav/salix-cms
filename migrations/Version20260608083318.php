<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608083318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE content_block (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, position INT NOT NULL, data JSON NOT NULL, page_id INT NOT NULL, INDEX IDX_68D8C3F0C4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE content_block ADD CONSTRAINT FK_68D8C3F0C4663E4 FOREIGN KEY (page_id) REFERENCES content_page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_page DROP content');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE content_block DROP FOREIGN KEY FK_68D8C3F0C4663E4');
        $this->addSql('DROP TABLE content_block');
        $this->addSql('ALTER TABLE content_page ADD content LONGTEXT NOT NULL');
    }
}
