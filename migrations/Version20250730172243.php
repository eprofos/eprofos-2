<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730172243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE exercise_submission (id SERIAL NOT NULL, exercise_id INT NOT NULL, student_id INT NOT NULL, submission_data JSON NOT NULL, feedback TEXT DEFAULT NULL, score INT DEFAULT NULL, auto_score INT DEFAULT NULL, manual_score INT DEFAULT NULL, status VARCHAR(50) NOT NULL, type VARCHAR(50) NOT NULL, attempt_number INT NOT NULL, time_spent_minutes INT DEFAULT NULL, passed BOOLEAN NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, submitted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, graded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9B748693E934951A ON exercise_submission (exercise_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9B748693CB944F1A ON exercise_submission (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN exercise_submission.started_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN exercise_submission.submitted_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN exercise_submission.graded_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN exercise_submission.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN exercise_submission.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE qcmattempt (id SERIAL NOT NULL, qcm_id INT NOT NULL, student_id INT NOT NULL, answers JSON NOT NULL, score INT NOT NULL, max_score INT NOT NULL, time_spent INT NOT NULL, attempt_number INT NOT NULL, status VARCHAR(50) NOT NULL, passed BOOLEAN NOT NULL, question_scores JSON DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_518153C3FF6241A6 ON qcmattempt (qcm_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_518153C3CB944F1A ON qcmattempt (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN qcmattempt.started_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN qcmattempt.completed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN qcmattempt.expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN qcmattempt.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN qcmattempt.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise_submission ADD CONSTRAINT FK_9B748693E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise_submission ADD CONSTRAINT FK_9B748693CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qcmattempt ADD CONSTRAINT FK_518153C3FF6241A6 FOREIGN KEY (qcm_id) REFERENCES qcm (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qcmattempt ADD CONSTRAINT FK_518153C3CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ALTER streak_days DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise_submission DROP CONSTRAINT FK_9B748693E934951A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise_submission DROP CONSTRAINT FK_9B748693CB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qcmattempt DROP CONSTRAINT FK_518153C3FF6241A6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qcmattempt DROP CONSTRAINT FK_518153C3CB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE exercise_submission
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE qcmattempt
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ALTER streak_days SET DEFAULT 0
        SQL);
    }
}
