<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720070346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove DocumentAttachment entity and drop document_attachments table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP SEQUENCE document_attachments_id_seq CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_attachments DROP CONSTRAINT fk_c56dcdbca2b28fe8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_attachments DROP CONSTRAINT fk_c56dcdbcc33f7837
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_attachments
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE document_attachments_id_seq INCREMENT BY 1 MINVALUE 1 START 1
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_attachments (id SERIAL NOT NULL, document_id INT NOT NULL, uploaded_by_id INT DEFAULT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size BIGINT NOT NULL, checksum VARCHAR(100) DEFAULT NULL, attachment_type VARCHAR(50) NOT NULL, description TEXT DEFAULT NULL, is_public BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, download_count INT NOT NULL, sort_order INT NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_accessed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_c56dcdbca2b28fe8 ON document_attachments (uploaded_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_c56dcdbcc33f7837 ON document_attachments (document_id)
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
            ALTER TABLE document_attachments ADD CONSTRAINT fk_c56dcdbca2b28fe8 FOREIGN KEY (uploaded_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_attachments ADD CONSTRAINT fk_c56dcdbcc33f7837 FOREIGN KEY (document_id) REFERENCES documents (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }
}
