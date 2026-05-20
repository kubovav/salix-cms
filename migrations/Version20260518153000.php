<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add content_page table for CMS pages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE content_page (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(180) NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, published TINYINT(1) NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CONTENT_PAGE_SLUG (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content_page');
    }
}