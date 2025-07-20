<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720072252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP SEQUENCE document_access_id_seq CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_access DROP CONSTRAINT fk_b80b9a32b03a8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_access DROP CONSTRAINT fk_b80b9a32c33f7837
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_access
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP file_path
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE document_access_id_seq INCREMENT BY 1 MINVALUE 1 START 1
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_access (id SERIAL NOT NULL, document_id INT NOT NULL, created_by_id INT DEFAULT NULL, access_type VARCHAR(50) NOT NULL, access_value VARCHAR(255) DEFAULT NULL, permissions JSON DEFAULT NULL, access_token VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, description TEXT DEFAULT NULL, restrictions JSON DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_b80b9a32b03a8386 ON document_access (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_b80b9a32c33f7837 ON document_access (document_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_access.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_access.expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_access ADD CONSTRAINT fk_b80b9a32b03a8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_access ADD CONSTRAINT fk_b80b9a32c33f7837 FOREIGN KEY (document_id) REFERENCES documents (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD file_path VARCHAR(255) DEFAULT NULL
        SQL);
    }
}
