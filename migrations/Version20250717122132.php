<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250717122132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE legal_documents (id SERIAL NOT NULL, type VARCHAR(100) NOT NULL, title VARCHAR(255) NOT NULL, content TEXT NOT NULL, file_path VARCHAR(255) DEFAULT NULL, version VARCHAR(50) NOT NULL, is_active BOOLEAN NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN legal_documents.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN legal_documents.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_c9af7fed182060a RENAME TO IDX_E1A04AC6D182060A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_c9af7fee182060a RENAME TO IDX_FCFD4E0CD182060A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations ADD documents_delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations ADD documents_acknowledged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations ADD document_acknowledgment_token VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE legal_documents
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_e1a04ac6d182060a RENAME TO idx_c9af7fed182060a
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations DROP documents_delivered_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations DROP documents_acknowledged_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations DROP document_acknowledgment_token
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_fcfd4e0cd182060a RENAME TO idx_c9af7fee182060a
        SQL);
    }
}
