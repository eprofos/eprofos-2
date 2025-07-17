<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250717083518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE session_registrations (id SERIAL NOT NULL, session_id INT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) DEFAULT NULL, company VARCHAR(150) DEFAULT NULL, position VARCHAR(100) DEFAULT NULL, status VARCHAR(50) NOT NULL, notes TEXT DEFAULT NULL, special_requirements TEXT DEFAULT NULL, additional_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C9AF7FEC613FECDF ON session_registrations (session_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN session_registrations.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN session_registrations.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (id SERIAL NOT NULL, formation_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, registration_deadline DATE DEFAULT NULL, location VARCHAR(255) NOT NULL, address VARCHAR(500) DEFAULT NULL, max_capacity INT NOT NULL, min_capacity INT NOT NULL, current_registrations INT NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(50) NOT NULL, is_active BOOLEAN NOT NULL, instructor VARCHAR(100) DEFAULT NULL, notes TEXT DEFAULT NULL, additional_info JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9A609D135200282E ON sessions (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN sessions.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN sessions.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations ADD CONSTRAINT FK_C9AF7FEC613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions ADD CONSTRAINT FK_9A609D135200282E FOREIGN KEY (formation_id) REFERENCES formation (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations DROP CONSTRAINT FK_C9AF7FEC613FECDF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions DROP CONSTRAINT FK_9A609D135200282E
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE session_registrations
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE sessions
        SQL);
    }
}
