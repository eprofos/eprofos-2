<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250622191422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE company_needs_analyses (id SERIAL NOT NULL, needs_analysis_request_id INT NOT NULL, company_name VARCHAR(255) NOT NULL, responsible_person VARCHAR(255) NOT NULL, contact_email VARCHAR(180) NOT NULL, contact_phone VARCHAR(20) NOT NULL, company_address TEXT NOT NULL, activity_sector VARCHAR(255) NOT NULL, naf_code VARCHAR(10) DEFAULT NULL, siret VARCHAR(14) DEFAULT NULL, employee_count INT NOT NULL, opco VARCHAR(255) DEFAULT NULL, trainees_info JSON NOT NULL, training_title VARCHAR(255) NOT NULL, training_duration_hours INT NOT NULL, preferred_start_date DATE DEFAULT NULL, preferred_end_date DATE DEFAULT NULL, training_location_preference VARCHAR(255) NOT NULL, location_appropriation_needs TEXT DEFAULT NULL, disability_accommodations TEXT DEFAULT NULL, training_expectations TEXT NOT NULL, specific_needs TEXT NOT NULL, submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_8224FC4BB3BF07B2 ON company_needs_analyses (needs_analysis_request_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN company_needs_analyses.submitted_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE individual_needs_analyses (id SERIAL NOT NULL, needs_analysis_request_id INT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, address TEXT NOT NULL, phone VARCHAR(20) NOT NULL, email VARCHAR(180) NOT NULL, status VARCHAR(20) NOT NULL, status_other_details TEXT DEFAULT NULL, funding_type VARCHAR(20) NOT NULL, funding_other_details TEXT DEFAULT NULL, desired_training_title VARCHAR(255) NOT NULL, professional_objective TEXT NOT NULL, current_level VARCHAR(20) NOT NULL, desired_duration_hours INT NOT NULL, preferred_start_date DATE DEFAULT NULL, preferred_end_date DATE DEFAULT NULL, training_location_preference VARCHAR(255) NOT NULL, disability_accommodations TEXT DEFAULT NULL, training_expectations TEXT NOT NULL, specific_needs TEXT NOT NULL, submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_51E7BAD4B3BF07B2 ON individual_needs_analyses (needs_analysis_request_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN individual_needs_analyses.submitted_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE needs_analysis_requests (id SERIAL NOT NULL, created_by_user_id INT NOT NULL, formation_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, token VARCHAR(36) NOT NULL, recipient_email VARCHAR(180) NOT NULL, recipient_name VARCHAR(255) NOT NULL, company_name VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, admin_notes TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_FCFD4E0C5F37A13B ON needs_analysis_requests (token)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FCFD4E0C7D182D95 ON needs_analysis_requests (created_by_user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FCFD4E0C5200282E ON needs_analysis_requests (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN needs_analysis_requests.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN needs_analysis_requests.sent_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN needs_analysis_requests.completed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN needs_analysis_requests.expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_needs_analyses ADD CONSTRAINT FK_8224FC4BB3BF07B2 FOREIGN KEY (needs_analysis_request_id) REFERENCES needs_analysis_requests (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE individual_needs_analyses ADD CONSTRAINT FK_51E7BAD4B3BF07B2 FOREIGN KEY (needs_analysis_request_id) REFERENCES needs_analysis_requests (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests ADD CONSTRAINT FK_FCFD4E0C7D182D95 FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests ADD CONSTRAINT FK_FCFD4E0C5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_needs_analyses DROP CONSTRAINT FK_8224FC4BB3BF07B2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE individual_needs_analyses DROP CONSTRAINT FK_51E7BAD4B3BF07B2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests DROP CONSTRAINT FK_FCFD4E0C7D182D95
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests DROP CONSTRAINT FK_FCFD4E0C5200282E
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE company_needs_analyses
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE individual_needs_analyses
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE needs_analysis_requests
        SQL);
    }
}
