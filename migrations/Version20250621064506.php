<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250621064506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP SEQUENCE contact_request_id_seq CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE contact_requests (id SERIAL NOT NULL, formation_id INT DEFAULT NULL, service_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) DEFAULT NULL, company VARCHAR(150) DEFAULT NULL, subject VARCHAR(200) DEFAULT NULL, message TEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, additional_data JSON DEFAULT NULL, admin_notes TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E1A04AC65200282E ON contact_requests (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E1A04AC6ED5CA9E6 ON contact_requests (service_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_requests ADD CONSTRAINT FK_E1A04AC65200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_requests ADD CONSTRAINT FK_E1A04AC6ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_request DROP CONSTRAINT fk_a1b8ae1e5200282e
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE contact_request
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE contact_request_id_seq INCREMENT BY 1 MINVALUE 1 START 1
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE contact_request (id SERIAL NOT NULL, formation_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(20) DEFAULT NULL, company VARCHAR(255) DEFAULT NULL, message TEXT NOT NULL, status VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_a1b8ae1e5200282e ON contact_request (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN contact_request.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN contact_request.processed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_request ADD CONSTRAINT fk_a1b8ae1e5200282e FOREIGN KEY (formation_id) REFERENCES formation (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_requests DROP CONSTRAINT FK_E1A04AC65200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_requests DROP CONSTRAINT FK_E1A04AC6ED5CA9E6
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE contact_requests
        SQL);
    }
}
