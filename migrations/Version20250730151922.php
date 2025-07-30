<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730151922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE student_enrollments (id SERIAL NOT NULL, student_id INT NOT NULL, session_registration_id INT NOT NULL, progress_id INT DEFAULT NULL, status VARCHAR(20) NOT NULL, enrolled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, dropout_reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, metadata JSON DEFAULT NULL, enrollment_source VARCHAR(50) DEFAULT NULL, admin_notes TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1B38CC31CB944F1A ON student_enrollments (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_1B38CC31BC1FC136 ON student_enrollments (session_registration_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_1B38CC3143DB87C9 ON student_enrollments (progress_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_enrollment_status ON student_enrollments (status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_enrolled_at ON student_enrollments (enrolled_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX unique_student_session_registration ON student_enrollments (student_id, session_registration_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_enrollments.enrolled_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_enrollments.completed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_enrollments.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_enrollments.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_enrollments ADD CONSTRAINT FK_1B38CC31CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_enrollments ADD CONSTRAINT FK_1B38CC31BC1FC136 FOREIGN KEY (session_registration_id) REFERENCES session_registrations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_enrollments ADD CONSTRAINT FK_1B38CC3143DB87C9 FOREIGN KEY (progress_id) REFERENCES student_progress (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_enrollments DROP CONSTRAINT FK_1B38CC31CB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_enrollments DROP CONSTRAINT FK_1B38CC31BC1FC136
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_enrollments DROP CONSTRAINT FK_1B38CC3143DB87C9
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE student_enrollments
        SQL);
    }
}
