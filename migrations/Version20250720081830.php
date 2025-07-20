<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720081830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy legal_documents table after migration to new document management system';
    }

    public function up(Schema $schema): void
    {
        // Only drop if the table exists
        $this->addSql(<<<'SQL'
            DROP TABLE IF EXISTS legal_documents CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP SEQUENCE IF EXISTS legal_documents_id_seq CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Recreate legacy table structure for rollback (not recommended - data will be lost)
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE legal_documents_id_seq INCREMENT BY 1 MINVALUE 1 START 1
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE legal_documents (
                id SERIAL NOT NULL, 
                type VARCHAR(100) NOT NULL, 
                title VARCHAR(255) NOT NULL, 
                content TEXT NOT NULL, 
                version VARCHAR(50) NOT NULL, 
                is_active BOOLEAN NOT NULL, 
                metadata JSON DEFAULT NULL, 
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
                published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, 
                status VARCHAR(20) NOT NULL, 
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN legal_documents.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN legal_documents.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
    }
}
