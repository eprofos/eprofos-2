<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726202555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE company_missions (id SERIAL NOT NULL, supervisor_id INT NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, context TEXT NOT NULL, objectives JSON NOT NULL, required_skills JSON NOT NULL, skills_to_acquire JSON NOT NULL, duration VARCHAR(100) NOT NULL, complexity VARCHAR(50) NOT NULL, term VARCHAR(50) NOT NULL, order_index INT NOT NULL, department VARCHAR(150) NOT NULL, prerequisites JSON NOT NULL, evaluation_criteria JSON NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2C66C11319E9AC5F ON company_missions (supervisor_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN company_missions.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN company_missions.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mission_assignments (id SERIAL NOT NULL, student_id INT NOT NULL, mission_id INT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(50) NOT NULL, intermediate_objectives JSON NOT NULL, completion_rate NUMERIC(5, 2) NOT NULL, difficulties JSON NOT NULL, achievements JSON NOT NULL, mentor_feedback TEXT DEFAULT NULL, student_feedback TEXT DEFAULT NULL, mentor_rating INT DEFAULT NULL, student_satisfaction INT DEFAULT NULL, competencies_acquired JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_updated TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_57151A3CCB944F1A ON mission_assignments (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_57151A3CBE6CAE90 ON mission_assignments (mission_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN mission_assignments.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN mission_assignments.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN mission_assignments.last_updated IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_missions ADD CONSTRAINT FK_2C66C11319E9AC5F FOREIGN KEY (supervisor_id) REFERENCES mentors (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mission_assignments ADD CONSTRAINT FK_57151A3CCB944F1A FOREIGN KEY (student_id) REFERENCES students (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mission_assignments ADD CONSTRAINT FK_57151A3CBE6CAE90 FOREIGN KEY (mission_id) REFERENCES company_missions (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_missions DROP CONSTRAINT FK_2C66C11319E9AC5F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mission_assignments DROP CONSTRAINT FK_57151A3CCB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mission_assignments DROP CONSTRAINT FK_57151A3CBE6CAE90
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE company_missions
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mission_assignments
        SQL);
    }
}
