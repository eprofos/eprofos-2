<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250718224820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE students (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, birth_date DATE DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, education_level VARCHAR(100) DEFAULT NULL, profession VARCHAR(100) DEFAULT NULL, company VARCHAR(100) DEFAULT NULL, is_active BOOLEAN NOT NULL, email_verified BOOLEAN NOT NULL, email_verification_token VARCHAR(100) DEFAULT NULL, email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, password_reset_token VARCHAR(100) DEFAULT NULL, password_reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_A4698DB2E7927C74 ON students (email)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN students.email_verified_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN students.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN students.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN students.last_login_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN students.password_reset_token_expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_chapter_duration_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_chapter_module_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_chapter_order
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_course_chapter_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_course_duration_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_course_order
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_exercise_course_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_exercise_duration_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_exercise_order
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_formation_duration_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_module_duration_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_module_formation_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_module_order
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_qcm_course_active
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_qcm_order
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_qcm_timelimit_active
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE students
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_course_chapter_active ON course (chapter_id, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_course_duration_active ON course (duration_minutes, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_course_order ON course (chapter_id, order_index)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_module_duration_active ON module (duration_hours, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_module_formation_active ON module (formation_id, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_module_order ON module (formation_id, order_index)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_qcm_course_active ON qcm (course_id, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_qcm_order ON qcm (course_id, order_index)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_qcm_timelimit_active ON qcm (time_limit_minutes, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_chapter_duration_active ON chapter (duration_minutes, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_chapter_module_active ON chapter (module_id, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_chapter_order ON chapter (module_id, order_index)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_exercise_course_active ON exercise (course_id, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_exercise_duration_active ON exercise (estimated_duration_minutes, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_exercise_order ON exercise (course_id, order_index)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_formation_duration_active ON formation (duration_hours, is_active)
        SQL);
    }
}
