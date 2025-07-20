<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720102635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE document_ui_components (id SERIAL NOT NULL, ui_template_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, zone VARCHAR(50) NOT NULL, content TEXT DEFAULT NULL, html_content TEXT DEFAULT NULL, style_config JSON DEFAULT NULL, position_config JSON DEFAULT NULL, data_binding JSON DEFAULT NULL, conditional_display JSON DEFAULT NULL, is_active BOOLEAN NOT NULL, is_required BOOLEAN NOT NULL, sort_order INT NOT NULL, css_class VARCHAR(50) DEFAULT NULL, element_id VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC7FCF6E16A30F70 ON document_ui_components (ui_template_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC7FCF6EB03A8386 ON document_ui_components (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC7FCF6E896DBBDE ON document_ui_components (updated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_ui_components.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_ui_components.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_ui_templates (id SERIAL NOT NULL, document_type_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(500) NOT NULL, description TEXT DEFAULT NULL, html_template TEXT DEFAULT NULL, css_styles TEXT DEFAULT NULL, layout_configuration JSON DEFAULT NULL, page_settings JSON DEFAULT NULL, header_footer_config JSON DEFAULT NULL, component_styles JSON DEFAULT NULL, variables JSON DEFAULT NULL, orientation VARCHAR(50) NOT NULL, paper_size VARCHAR(50) NOT NULL, margins JSON DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, is_global BOOLEAN NOT NULL, sort_order INT NOT NULL, usage_count INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_807E0DA2989D9B62 ON document_ui_templates (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_807E0DA261232A4F ON document_ui_templates (document_type_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_807E0DA2B03A8386 ON document_ui_templates (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_807E0DA2896DBBDE ON document_ui_templates (updated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_ui_templates.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_ui_templates.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components ADD CONSTRAINT FK_DC7FCF6E16A30F70 FOREIGN KEY (ui_template_id) REFERENCES document_ui_templates (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components ADD CONSTRAINT FK_DC7FCF6EB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components ADD CONSTRAINT FK_DC7FCF6E896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD CONSTRAINT FK_807E0DA261232A4F FOREIGN KEY (document_type_id) REFERENCES document_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD CONSTRAINT FK_807E0DA2B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD CONSTRAINT FK_807E0DA2896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components DROP CONSTRAINT FK_DC7FCF6E16A30F70
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components DROP CONSTRAINT FK_DC7FCF6EB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components DROP CONSTRAINT FK_DC7FCF6E896DBBDE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP CONSTRAINT FK_807E0DA261232A4F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP CONSTRAINT FK_807E0DA2B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP CONSTRAINT FK_807E0DA2896DBBDE
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_ui_components
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_ui_templates
        SQL);
    }
}
