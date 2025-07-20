<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720104322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD margin_top NUMERIC(5, 1) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD margin_right NUMERIC(5, 1) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD margin_bottom NUMERIC(5, 1) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD margin_left NUMERIC(5, 1) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD show_page_numbers BOOLEAN NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD custom_css TEXT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP margin_top
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP margin_right
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP margin_bottom
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP margin_left
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP show_page_numbers
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP custom_css
        SQL);
    }
}
