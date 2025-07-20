<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720192745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE attendance_records (id SERIAL NOT NULL, student_id INT NOT NULL, session_id INT NOT NULL, status VARCHAR(20) NOT NULL, participation_score INT NOT NULL, absence_reason TEXT DEFAULT NULL, excused BOOLEAN NOT NULL, admin_notes TEXT DEFAULT NULL, arrival_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL, departure_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL, minutes_late INT DEFAULT NULL, minutes_early_departure INT DEFAULT NULL, metadata JSON DEFAULT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, recorded_by VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9B5AB644CB944F1A ON attendance_records (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9B5AB644613FECDF ON attendance_records (session_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_status ON attendance_records (status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_recorded_at ON attendance_records (recorded_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX unique_student_session ON attendance_records (student_id, session_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attendance_records.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attendance_records.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE student_progress (id SERIAL NOT NULL, student_id INT NOT NULL, formation_id INT NOT NULL, current_module_id INT DEFAULT NULL, current_chapter_id INT DEFAULT NULL, completion_percentage NUMERIC(5, 2) NOT NULL, module_progress JSON DEFAULT NULL, chapter_progress JSON DEFAULT NULL, last_activity TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, engagement_score INT NOT NULL, difficulty_signals JSON DEFAULT NULL, at_risk_of_dropout BOOLEAN NOT NULL, risk_score NUMERIC(5, 2) NOT NULL, total_time_spent INT NOT NULL, login_count INT NOT NULL, average_session_duration NUMERIC(8, 2) DEFAULT NULL, attendance_rate NUMERIC(5, 2) NOT NULL, missed_sessions INT NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_risk_assessment TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_918ABEDDCB944F1A ON student_progress (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_918ABEDD5200282E ON student_progress (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_918ABEDD74E4043D ON student_progress (current_module_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_918ABEDD88248E1A ON student_progress (current_chapter_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_student_formation ON student_progress (student_id, formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_at_risk ON student_progress (at_risk_of_dropout)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_last_activity ON student_progress (last_activity)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_progress.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_progress.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD CONSTRAINT FK_9B5AB644CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD CONSTRAINT FK_9B5AB644613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD CONSTRAINT FK_918ABEDDCB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD CONSTRAINT FK_918ABEDD5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD CONSTRAINT FK_918ABEDD74E4043D FOREIGN KEY (current_module_id) REFERENCES module (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD CONSTRAINT FK_918ABEDD88248E1A FOREIGN KEY (current_chapter_id) REFERENCES chapter (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_log_action
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_log_class_lookup
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_log_date
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_log_user
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_log_version
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP CONSTRAINT FK_9B5AB644CB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records DROP CONSTRAINT FK_9B5AB644613FECDF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP CONSTRAINT FK_918ABEDDCB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP CONSTRAINT FK_918ABEDD5200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP CONSTRAINT FK_918ABEDD74E4043D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP CONSTRAINT FK_918ABEDD88248E1A
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE attendance_records
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE student_progress
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_log_action ON ext_log_entries (action)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_log_class_lookup ON ext_log_entries (object_class, object_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_log_date ON ext_log_entries (logged_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_log_user ON ext_log_entries (username)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_log_version ON ext_log_entries (object_class, object_id, version)
        SQL);
    }
}
