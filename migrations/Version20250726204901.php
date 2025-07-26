<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726204901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE progress_assessments (id SERIAL NOT NULL, student_id INT NOT NULL, period DATE NOT NULL, center_progression NUMERIC(5, 2) NOT NULL, company_progression NUMERIC(5, 2) NOT NULL, overall_progression NUMERIC(5, 2) NOT NULL, completed_objectives JSON NOT NULL, pending_objectives JSON NOT NULL, upcoming_objectives JSON NOT NULL, difficulties JSON NOT NULL, support_needed JSON NOT NULL, next_steps TEXT DEFAULT NULL, skills_matrix JSON NOT NULL, risk_level INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_58755352CB944F1A ON progress_assessments (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_student_period ON progress_assessments (student_id, period)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_risk_level ON progress_assessments (risk_level)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_overall_progression ON progress_assessments (overall_progression)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN progress_assessments.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN progress_assessments.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE skills_assessments (id SERIAL NOT NULL, student_id INT NOT NULL, center_evaluator_id INT DEFAULT NULL, mentor_evaluator_id INT DEFAULT NULL, related_mission_id INT DEFAULT NULL, assessment_type VARCHAR(50) NOT NULL, context VARCHAR(50) NOT NULL, assessment_date DATE NOT NULL, skills_evaluated JSON NOT NULL, center_scores JSON NOT NULL, company_scores JSON NOT NULL, global_competencies JSON NOT NULL, center_comments TEXT DEFAULT NULL, mentor_comments TEXT DEFAULT NULL, development_plan JSON NOT NULL, overall_rating VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_AEE93CF8CB944F1A ON skills_assessments (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_AEE93CF844B5DF02 ON skills_assessments (center_evaluator_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_AEE93CF8B7C7B482 ON skills_assessments (mentor_evaluator_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_AEE93CF834A1A620 ON skills_assessments (related_mission_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_student_assessment_date ON skills_assessments (student_id, assessment_date)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_context ON skills_assessments (context)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_assessment_type ON skills_assessments (assessment_type)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN skills_assessments.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN skills_assessments.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE progress_assessments ADD CONSTRAINT FK_58755352CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skills_assessments ADD CONSTRAINT FK_AEE93CF8CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skills_assessments ADD CONSTRAINT FK_AEE93CF844B5DF02 FOREIGN KEY (center_evaluator_id) REFERENCES teachers (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skills_assessments ADD CONSTRAINT FK_AEE93CF8B7C7B482 FOREIGN KEY (mentor_evaluator_id) REFERENCES mentors (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skills_assessments ADD CONSTRAINT FK_AEE93CF834A1A620 FOREIGN KEY (related_mission_id) REFERENCES mission_assignments (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD alternance_contract_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD center_completion_rate NUMERIC(5, 2) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD company_completion_rate NUMERIC(5, 2) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD mission_progress JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD skills_acquired JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD alternance_status VARCHAR(50) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD alternance_risk_score INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD CONSTRAINT FK_918ABEDD58BCE027 FOREIGN KEY (alternance_contract_id) REFERENCES alternance_contracts (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_918ABEDD58BCE027 ON student_progress (alternance_contract_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE progress_assessments DROP CONSTRAINT FK_58755352CB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skills_assessments DROP CONSTRAINT FK_AEE93CF8CB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skills_assessments DROP CONSTRAINT FK_AEE93CF844B5DF02
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skills_assessments DROP CONSTRAINT FK_AEE93CF8B7C7B482
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE skills_assessments DROP CONSTRAINT FK_AEE93CF834A1A620
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE progress_assessments
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE skills_assessments
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP CONSTRAINT FK_918ABEDD58BCE027
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_918ABEDD58BCE027
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP alternance_contract_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP center_completion_rate
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP company_completion_rate
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP mission_progress
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP skills_acquired
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP alternance_status
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP alternance_risk_score
        SQL);
    }
}
