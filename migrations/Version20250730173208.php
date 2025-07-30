<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730173208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE student_certificates (id SERIAL NOT NULL, student_id INT NOT NULL, formation_id INT NOT NULL, enrollment_id INT NOT NULL, certificate_number VARCHAR(100) NOT NULL, verification_code VARCHAR(64) NOT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(20) NOT NULL, completion_data JSON NOT NULL, certificate_template VARCHAR(50) NOT NULL, pdf_path VARCHAR(255) DEFAULT NULL, metadata JSON NOT NULL, grade VARCHAR(2) NOT NULL, final_score NUMERIC(5, 2) NOT NULL, revocation_reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_14F84AE83005EFE3 ON student_certificates (certificate_number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_14F84AE8E821C39F ON student_certificates (verification_code)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_14F84AE88F7DB25B ON student_certificates (enrollment_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_certificate_student ON student_certificates (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_certificate_formation ON student_certificates (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_certificate_number ON student_certificates (certificate_number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_verification_code ON student_certificates (verification_code)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_certificate_status ON student_certificates (status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_issued_at ON student_certificates (issued_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX unique_student_formation_certificate ON student_certificates (student_id, formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_certificates.issued_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_certificates.revoked_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_certificates.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN student_certificates.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_certificates ADD CONSTRAINT FK_14F84AE8CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_certificates ADD CONSTRAINT FK_14F84AE85200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_certificates ADD CONSTRAINT FK_14F84AE88F7DB25B FOREIGN KEY (enrollment_id) REFERENCES student_enrollments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_certificates DROP CONSTRAINT FK_14F84AE8CB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_certificates DROP CONSTRAINT FK_14F84AE85200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_certificates DROP CONSTRAINT FK_14F84AE88F7DB25B
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE student_certificates
        SQL);
    }
}
