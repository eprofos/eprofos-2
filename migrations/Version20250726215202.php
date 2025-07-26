<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726215202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE company_visits (id SERIAL NOT NULL, student_id INT NOT NULL, visitor_id INT NOT NULL, mentor_id INT NOT NULL, visit_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, visit_type VARCHAR(50) NOT NULL, objectives_checked JSON NOT NULL, observed_activities JSON NOT NULL, strengths JSON NOT NULL, improvement_areas JSON NOT NULL, mentor_feedback TEXT DEFAULT NULL, student_feedback TEXT DEFAULT NULL, recommendations JSON NOT NULL, visit_report TEXT DEFAULT NULL, follow_up_required BOOLEAN NOT NULL, next_visit_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, overall_rating INT DEFAULT NULL, working_conditions_rating INT DEFAULT NULL, supervision_rating INT DEFAULT NULL, integration_rating INT DEFAULT NULL, notes TEXT DEFAULT NULL, duration INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_79D75D9F70BEE6D ON company_visits (visitor_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_79D75D9FDB403044 ON company_visits (mentor_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_visit_student ON company_visits (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_visit_date ON company_visits (visit_date)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_visit_type ON company_visits (visit_type)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_visit_follow_up ON company_visits (follow_up_required)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN company_visits.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN company_visits.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE coordination_meetings (id SERIAL NOT NULL, student_id INT NOT NULL, pedagogical_supervisor_id INT NOT NULL, mentor_id INT NOT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(50) NOT NULL, location VARCHAR(50) NOT NULL, agenda JSON NOT NULL, discussion_points JSON NOT NULL, decisions JSON NOT NULL, action_plan JSON NOT NULL, next_meeting_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, meeting_report TEXT DEFAULT NULL, status VARCHAR(30) NOT NULL, attendees JSON NOT NULL, duration INT DEFAULT NULL, notes TEXT DEFAULT NULL, satisfaction_rating INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FBCC898F68C8A426 ON coordination_meetings (pedagogical_supervisor_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FBCC898FDB403044 ON coordination_meetings (mentor_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_coordination_student ON coordination_meetings (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_coordination_date ON coordination_meetings (date)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_coordination_status ON coordination_meetings (status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_coordination_type ON coordination_meetings (type)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN coordination_meetings.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN coordination_meetings.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_visits ADD CONSTRAINT FK_79D75D9FCB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_visits ADD CONSTRAINT FK_79D75D9F70BEE6D FOREIGN KEY (visitor_id) REFERENCES teachers (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_visits ADD CONSTRAINT FK_79D75D9FDB403044 FOREIGN KEY (mentor_id) REFERENCES mentors (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE coordination_meetings ADD CONSTRAINT FK_FBCC898FCB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE coordination_meetings ADD CONSTRAINT FK_FBCC898F68C8A426 FOREIGN KEY (pedagogical_supervisor_id) REFERENCES teachers (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE coordination_meetings ADD CONSTRAINT FK_FBCC898FDB403044 FOREIGN KEY (mentor_id) REFERENCES mentors (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD related_mission_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD supervising_mentor_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD attendance_location VARCHAR(20) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD company_evaluation_criteria JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD company_notes TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD company_rating DOUBLE PRECISION DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD CONSTRAINT FK_9B5AB64434A1A620 FOREIGN KEY (related_mission_id) REFERENCES company_missions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD CONSTRAINT FK_9B5AB64457E1EB64 FOREIGN KEY (supervising_mentor_id) REFERENCES mentors (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9B5AB64434A1A620 ON attendance_records (related_mission_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9B5AB64457E1EB64 ON attendance_records (supervising_mentor_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_visits DROP CONSTRAINT FK_79D75D9FCB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_visits DROP CONSTRAINT FK_79D75D9F70BEE6D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_visits DROP CONSTRAINT FK_79D75D9FDB403044
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE coordination_meetings DROP CONSTRAINT FK_FBCC898FCB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE coordination_meetings DROP CONSTRAINT FK_FBCC898F68C8A426
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE coordination_meetings DROP CONSTRAINT FK_FBCC898FDB403044
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE company_visits
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE coordination_meetings
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP CONSTRAINT FK_9B5AB64434A1A620
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP CONSTRAINT FK_9B5AB64457E1EB64
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_9B5AB64434A1A620
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_9B5AB64457E1EB64
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP related_mission_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP supervising_mentor_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP attendance_location
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP company_evaluation_criteria
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP company_notes
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP company_rating
        SQL);
    }
}
