<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715095518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE category (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_64C19C1989D9B62 ON category (slug)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN category.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN category.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE chapter (id SERIAL NOT NULL, module_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, learning_objectives JSON DEFAULT NULL, content_outline TEXT DEFAULT NULL, prerequisites TEXT DEFAULT NULL, learning_outcomes JSON DEFAULT NULL, teaching_methods TEXT DEFAULT NULL, resources JSON DEFAULT NULL, assessment_methods TEXT DEFAULT NULL, success_criteria JSON DEFAULT NULL, duration_minutes INT NOT NULL, order_index INT NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_F981B52E989D9B62 ON chapter (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F981B52EAFC2B591 ON chapter (module_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN chapter.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN chapter.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
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
            CREATE TABLE contact_requests (id SERIAL NOT NULL, formation_id INT DEFAULT NULL, service_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) DEFAULT NULL, company VARCHAR(150) DEFAULT NULL, subject VARCHAR(200) DEFAULT NULL, message TEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, additional_data JSON DEFAULT NULL, admin_notes TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E1A04AC65200282E ON contact_requests (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E1A04AC6ED5CA9E6 ON contact_requests (service_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE course (id SERIAL NOT NULL, chapter_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, learning_objectives JSON DEFAULT NULL, content_outline TEXT DEFAULT NULL, prerequisites TEXT DEFAULT NULL, learning_outcomes JSON DEFAULT NULL, teaching_methods TEXT DEFAULT NULL, resources JSON DEFAULT NULL, assessment_methods TEXT DEFAULT NULL, success_criteria JSON DEFAULT NULL, content TEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, duration_minutes INT NOT NULL, order_index INT NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_169E6FB9989D9B62 ON course (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_169E6FB9579F4768 ON course (chapter_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN course.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN course.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE exercise (id SERIAL NOT NULL, course_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, instructions TEXT NOT NULL, expected_outcomes JSON DEFAULT NULL, evaluation_criteria JSON DEFAULT NULL, resources JSON DEFAULT NULL, prerequisites TEXT DEFAULT NULL, success_criteria JSON DEFAULT NULL, type VARCHAR(50) NOT NULL, difficulty VARCHAR(50) NOT NULL, estimated_duration_minutes INT NOT NULL, max_points INT DEFAULT NULL, passing_points INT DEFAULT NULL, order_index INT NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_AEDAD51C989D9B62 ON exercise (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_AEDAD51C591CC992 ON exercise (course_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN exercise.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN exercise.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE formation (id SERIAL NOT NULL, category_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, objectives TEXT DEFAULT NULL, operational_objectives JSON DEFAULT NULL, evaluable_objectives JSON DEFAULT NULL, evaluation_criteria JSON DEFAULT NULL, success_indicators JSON DEFAULT NULL, prerequisites TEXT DEFAULT NULL, duration_hours INT NOT NULL, price NUMERIC(10, 2) NOT NULL, level VARCHAR(50) NOT NULL, format VARCHAR(50) NOT NULL, is_active BOOLEAN NOT NULL, is_featured BOOLEAN NOT NULL, image_path VARCHAR(255) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, target_audience TEXT DEFAULT NULL, access_modalities TEXT DEFAULT NULL, handicap_accessibility TEXT DEFAULT NULL, teaching_methods TEXT DEFAULT NULL, evaluation_methods TEXT DEFAULT NULL, contact_info TEXT DEFAULT NULL, training_location TEXT DEFAULT NULL, funding_modalities TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_404021BF989D9B62 ON formation (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_404021BF12469DE2 ON formation (category_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN formation.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN formation.updated_at IS '(DC2Type:datetime_immutable)'
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
            CREATE TABLE module (id SERIAL NOT NULL, formation_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, learning_objectives JSON DEFAULT NULL, prerequisites TEXT DEFAULT NULL, duration_hours INT NOT NULL, order_index INT NOT NULL, evaluation_methods TEXT DEFAULT NULL, teaching_methods TEXT DEFAULT NULL, resources JSON DEFAULT NULL, success_criteria JSON DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_C242628989D9B62 ON module (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C2426285200282E ON module (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN module.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN module.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE needs_analysis_requests (id SERIAL NOT NULL, created_by_user_id INT NOT NULL, formation_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, token VARCHAR(36) NOT NULL, recipient_email VARCHAR(180) NOT NULL, recipient_name VARCHAR(255) NOT NULL, company_name VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, admin_notes TEXT DEFAULT NULL, PRIMARY KEY(id))
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
            COMMENT ON COLUMN needs_analysis_requests.last_reminder_sent_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE qcm (id SERIAL NOT NULL, course_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, instructions TEXT DEFAULT NULL, questions JSON NOT NULL, evaluation_criteria JSON DEFAULT NULL, success_criteria JSON DEFAULT NULL, time_limit_minutes INT DEFAULT NULL, max_score INT NOT NULL, passing_score INT NOT NULL, max_attempts INT NOT NULL, show_correct_answers BOOLEAN NOT NULL, show_explanations BOOLEAN NOT NULL, randomize_questions BOOLEAN NOT NULL, randomize_answers BOOLEAN NOT NULL, order_index INT NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_D7A1FEF4989D9B62 ON qcm (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D7A1FEF4591CC992 ON qcm (course_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN qcm.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN qcm.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE service (id SERIAL NOT NULL, service_category_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, benefits TEXT DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_E19D9AD2989D9B62 ON service (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E19D9AD2DEDCBB4E ON service (service_category_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN service.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN service.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE service_category (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_FF3A42FC989D9B62 ON service_category (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE users (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN users.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN users.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN users.last_login_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN messenger_messages.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN messenger_messages.available_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN messenger_messages.delivered_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
                BEGIN
                    PERFORM pg_notify('messenger_messages', NEW.queue_name::text);
                    RETURN NEW;
                END;
            $$ LANGUAGE plpgsql;
        SQL);
        $this->addSql(<<<'SQL'
            DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter ADD CONSTRAINT FK_F981B52EAFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_needs_analyses ADD CONSTRAINT FK_8224FC4BB3BF07B2 FOREIGN KEY (needs_analysis_request_id) REFERENCES needs_analysis_requests (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_requests ADD CONSTRAINT FK_E1A04AC65200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_requests ADD CONSTRAINT FK_E1A04AC6ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE course ADD CONSTRAINT FK_169E6FB9579F4768 FOREIGN KEY (chapter_id) REFERENCES chapter (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51C591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD CONSTRAINT FK_404021BF12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE individual_needs_analyses ADD CONSTRAINT FK_51E7BAD4B3BF07B2 FOREIGN KEY (needs_analysis_request_id) REFERENCES needs_analysis_requests (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE module ADD CONSTRAINT FK_C2426285200282E FOREIGN KEY (formation_id) REFERENCES formation (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests ADD CONSTRAINT FK_FCFD4E0C7D182D95 FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests ADD CONSTRAINT FK_FCFD4E0C5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qcm ADD CONSTRAINT FK_D7A1FEF4591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2DEDCBB4E FOREIGN KEY (service_category_id) REFERENCES service_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter DROP CONSTRAINT FK_F981B52EAFC2B591
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_needs_analyses DROP CONSTRAINT FK_8224FC4BB3BF07B2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_requests DROP CONSTRAINT FK_E1A04AC65200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_requests DROP CONSTRAINT FK_E1A04AC6ED5CA9E6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE course DROP CONSTRAINT FK_169E6FB9579F4768
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise DROP CONSTRAINT FK_AEDAD51C591CC992
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP CONSTRAINT FK_404021BF12469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE individual_needs_analyses DROP CONSTRAINT FK_51E7BAD4B3BF07B2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE module DROP CONSTRAINT FK_C2426285200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests DROP CONSTRAINT FK_FCFD4E0C7D182D95
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests DROP CONSTRAINT FK_FCFD4E0C5200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qcm DROP CONSTRAINT FK_D7A1FEF4591CC992
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service DROP CONSTRAINT FK_E19D9AD2DEDCBB4E
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE category
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE chapter
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE company_needs_analyses
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE contact_requests
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE course
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE exercise
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE formation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE individual_needs_analyses
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE module
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE needs_analysis_requests
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE qcm
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE service
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE service_category
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE users
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
    }
}
