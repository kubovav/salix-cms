<?php

declare(strict_types=1);

namespace Salix\Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260603131314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu_item (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, url VARCHAR(500) DEFAULT NULL, position INT NOT NULL, menu_name VARCHAR(50) NOT NULL, enabled TINYINT NOT NULL, page_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, INDEX IDX_D754D550C4663E4 (page_id), INDEX IDX_D754D550727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550C4663E4 FOREIGN KEY (page_id) REFERENCES content_page (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_page CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550C4663E4');
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550727ACA70');
        $this->addSql('DROP TABLE menu_item');
        $this->addSql('ALTER TABLE content_page CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
