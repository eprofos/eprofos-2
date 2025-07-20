<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250719182837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE document_access (id SERIAL NOT NULL, document_id INT NOT NULL, created_by_id INT DEFAULT NULL, access_type VARCHAR(50) NOT NULL, access_value VARCHAR(255) DEFAULT NULL, permissions JSON DEFAULT NULL, access_token VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, description TEXT DEFAULT NULL, restrictions JSON DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B80B9A32C33F7837 ON document_access (document_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B80B9A32B03A8386 ON document_access (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_access.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_access.expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_attachments (id SERIAL NOT NULL, document_id INT NOT NULL, uploaded_by_id INT DEFAULT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size BIGINT NOT NULL, checksum VARCHAR(100) DEFAULT NULL, attachment_type VARCHAR(50) NOT NULL, description TEXT DEFAULT NULL, is_public BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, download_count INT NOT NULL, sort_order INT NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_accessed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C56DCDBCC33F7837 ON document_attachments (document_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C56DCDBCA2B28FE8 ON document_attachments (uploaded_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_attachments.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_attachments.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_attachments.last_accessed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_categories (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(500) NOT NULL, description TEXT DEFAULT NULL, level INT NOT NULL, sort_order INT NOT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_9B30ED3E989D9B62 ON document_categories (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9B30ED3E727ACA70 ON document_categories (parent_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_categories.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_categories.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_metadata (id SERIAL NOT NULL, document_id INT NOT NULL, meta_key VARCHAR(100) NOT NULL, meta_value TEXT DEFAULT NULL, data_type VARCHAR(50) NOT NULL, is_required BOOLEAN NOT NULL, is_searchable BOOLEAN NOT NULL, is_editable BOOLEAN NOT NULL, validation_rules JSON DEFAULT NULL, display_name VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, sort_order INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C0D5C54DC33F7837 ON document_metadata (document_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_metadata.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_metadata.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_templates (id SERIAL NOT NULL, document_type_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(500) NOT NULL, description TEXT DEFAULT NULL, template_content TEXT DEFAULT NULL, default_metadata JSON DEFAULT NULL, placeholders JSON DEFAULT NULL, configuration JSON DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, sort_order INT NOT NULL, usage_count INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_7D10552F989D9B62 ON document_templates (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7D10552F61232A4F ON document_templates (document_type_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7D10552FB03A8386 ON document_templates (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7D10552F896DBBDE ON document_templates (updated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_templates.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_templates.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_types (id SERIAL NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, requires_approval BOOLEAN NOT NULL, allow_multiple_published BOOLEAN NOT NULL, has_expiration BOOLEAN NOT NULL, generates_pdf BOOLEAN NOT NULL, allowed_statuses JSON DEFAULT NULL, required_metadata JSON DEFAULT NULL, configuration JSON DEFAULT NULL, is_active BOOLEAN NOT NULL, sort_order INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_3091FF4277153098 ON document_types (code)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_types.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_types.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_versions (id SERIAL NOT NULL, document_id INT NOT NULL, created_by_id INT DEFAULT NULL, version VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, content TEXT DEFAULT NULL, change_log TEXT DEFAULT NULL, is_current BOOLEAN NOT NULL, file_size BIGINT DEFAULT NULL, checksum VARCHAR(100) DEFAULT NULL, additional_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_961DB18BC33F7837 ON document_versions (document_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_961DB18BB03A8386 ON document_versions (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_versions.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE documents (id SERIAL NOT NULL, document_type_id INT NOT NULL, category_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(500) NOT NULL, description TEXT DEFAULT NULL, content TEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, is_active BOOLEAN NOT NULL, is_public BOOLEAN NOT NULL, version VARCHAR(50) DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, tags JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_A2B07288989D9B62 ON documents (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B0728861232A4F ON documents (document_type_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B0728812469DE2 ON documents (category_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B07288B03A8386 ON documents (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B07288896DBBDE ON documents (updated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN documents.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN documents.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN documents.published_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN documents.expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_access ADD CONSTRAINT FK_B80B9A32C33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_access ADD CONSTRAINT FK_B80B9A32B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_attachments ADD CONSTRAINT FK_C56DCDBCC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_attachments ADD CONSTRAINT FK_C56DCDBCA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_categories ADD CONSTRAINT FK_9B30ED3E727ACA70 FOREIGN KEY (parent_id) REFERENCES document_categories (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_metadata ADD CONSTRAINT FK_C0D5C54DC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates ADD CONSTRAINT FK_7D10552F61232A4F FOREIGN KEY (document_type_id) REFERENCES document_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates ADD CONSTRAINT FK_7D10552FB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates ADD CONSTRAINT FK_7D10552F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_versions ADD CONSTRAINT FK_961DB18BC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_versions ADD CONSTRAINT FK_961DB18BB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B0728861232A4F FOREIGN KEY (document_type_id) REFERENCES document_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B0728812469DE2 FOREIGN KEY (category_id) REFERENCES document_categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_access DROP CONSTRAINT FK_B80B9A32C33F7837
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_access DROP CONSTRAINT FK_B80B9A32B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_attachments DROP CONSTRAINT FK_C56DCDBCC33F7837
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_attachments DROP CONSTRAINT FK_C56DCDBCA2B28FE8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_categories DROP CONSTRAINT FK_9B30ED3E727ACA70
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_metadata DROP CONSTRAINT FK_C0D5C54DC33F7837
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates DROP CONSTRAINT FK_7D10552F61232A4F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates DROP CONSTRAINT FK_7D10552FB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates DROP CONSTRAINT FK_7D10552F896DBBDE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_versions DROP CONSTRAINT FK_961DB18BC33F7837
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_versions DROP CONSTRAINT FK_961DB18BB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B0728861232A4F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B0728812469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B07288B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B07288896DBBDE
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_access
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_attachments
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_categories
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_metadata
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_templates
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_types
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_versions
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE documents
        SQL);
    }
}
