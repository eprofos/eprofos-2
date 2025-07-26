<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726183622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE alternance_contracts (id SERIAL NOT NULL, student_id INT NOT NULL, session_id INT NOT NULL, mentor_id INT NOT NULL, pedagogical_supervisor_id INT NOT NULL, company_name VARCHAR(255) NOT NULL, company_address TEXT NOT NULL, company_siret VARCHAR(14) NOT NULL, contract_type VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, job_title VARCHAR(255) NOT NULL, job_description TEXT NOT NULL, learning_objectives JSON NOT NULL, company_objectives JSON NOT NULL, weekly_center_hours INT NOT NULL, weekly_company_hours INT NOT NULL, remuneration VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, notes TEXT DEFAULT NULL, additional_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_403DB586CB944F1A ON alternance_contracts (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_403DB586613FECDF ON alternance_contracts (session_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_403DB586DB403044 ON alternance_contracts (mentor_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_403DB58668C8A426 ON alternance_contracts (pedagogical_supervisor_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN alternance_contracts.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN alternance_contracts.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN alternance_contracts.validated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN alternance_contracts.started_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN alternance_contracts.completed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE alternance_programs (id SERIAL NOT NULL, session_id INT NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, total_duration INT NOT NULL, center_duration INT NOT NULL, company_duration INT NOT NULL, center_modules JSON NOT NULL, company_modules JSON NOT NULL, coordination_points JSON NOT NULL, assessment_periods JSON NOT NULL, rhythm VARCHAR(255) NOT NULL, learning_progression JSON NOT NULL, notes TEXT DEFAULT NULL, additional_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_F512DDA3613FECDF ON alternance_programs (session_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN alternance_programs.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN alternance_programs.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD CONSTRAINT FK_403DB586CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD CONSTRAINT FK_403DB586613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD CONSTRAINT FK_403DB586DB403044 FOREIGN KEY (mentor_id) REFERENCES mentors (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD CONSTRAINT FK_403DB58668C8A426 FOREIGN KEY (pedagogical_supervisor_id) REFERENCES teachers (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_programs ADD CONSTRAINT FK_F512DDA3613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions ADD is_alternance_session BOOLEAN DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions ADD alternance_type VARCHAR(50) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions ADD minimum_alternance_duration INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions ADD center_percentage INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions ADD company_percentage INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions ADD alternance_prerequisites JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions ADD alternance_rhythm VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP CONSTRAINT FK_403DB586CB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP CONSTRAINT FK_403DB586613FECDF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP CONSTRAINT FK_403DB586DB403044
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP CONSTRAINT FK_403DB58668C8A426
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_programs DROP CONSTRAINT FK_F512DDA3613FECDF
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE alternance_contracts
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE alternance_programs
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions DROP is_alternance_session
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions DROP alternance_type
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions DROP minimum_alternance_duration
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions DROP center_percentage
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions DROP company_percentage
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions DROP alternance_prerequisites
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions DROP alternance_rhythm
        SQL);
    }
}
