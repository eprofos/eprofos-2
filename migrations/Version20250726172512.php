<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726172512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE admins (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_A2E0150FE7927C74 ON admins (email)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN admins.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN admins.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN admins.last_login_at IS '(DC2Type:datetime_immutable)'
        SQL);
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
            CREATE TABLE contact_requests (id SERIAL NOT NULL, formation_id INT DEFAULT NULL, service_id INT DEFAULT NULL, prospect_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) DEFAULT NULL, company VARCHAR(150) DEFAULT NULL, subject VARCHAR(200) DEFAULT NULL, message TEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, additional_data JSON DEFAULT NULL, admin_notes TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E1A04AC65200282E ON contact_requests (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E1A04AC6ED5CA9E6 ON contact_requests (service_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E1A04AC6D182060A ON contact_requests (prospect_id)
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
            CREATE TABLE document_categories (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(500) NOT NULL, description TEXT DEFAULT NULL, level INT NOT NULL, sort_order INT NOT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_9B30ED3E989D9B62 ON document_categories (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9B30ED3E727ACA70 ON document_categories (parent_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_categories.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_categories.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_metadata (id SERIAL NOT NULL, document_id INT NOT NULL, meta_key VARCHAR(100) NOT NULL, meta_value TEXT DEFAULT NULL, data_type VARCHAR(50) NOT NULL, is_required BOOLEAN NOT NULL, is_searchable BOOLEAN NOT NULL, is_editable BOOLEAN NOT NULL, validation_rules JSON DEFAULT NULL, display_name VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, sort_order INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C0D5C54DC33F7837 ON document_metadata (document_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_metadata.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_metadata.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_templates (id SERIAL NOT NULL, document_type_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(500) NOT NULL, description TEXT DEFAULT NULL, template_content TEXT DEFAULT NULL, default_metadata JSON DEFAULT NULL, placeholders JSON DEFAULT NULL, configuration JSON DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, sort_order INT NOT NULL, usage_count INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_7D10552F989D9B62 ON document_templates (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7D10552F61232A4F ON document_templates (document_type_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7D10552FB03A8386 ON document_templates (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7D10552F896DBBDE ON document_templates (updated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_templates.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_templates.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_types (id SERIAL NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, requires_approval BOOLEAN NOT NULL, allow_multiple_published BOOLEAN NOT NULL, has_expiration BOOLEAN NOT NULL, generates_pdf BOOLEAN NOT NULL, allowed_statuses JSON DEFAULT NULL, required_metadata JSON DEFAULT NULL, configuration JSON DEFAULT NULL, is_active BOOLEAN NOT NULL, sort_order INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_3091FF4277153098 ON document_types (code)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_types.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_types.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_ui_components (id SERIAL NOT NULL, ui_template_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, zone VARCHAR(50) NOT NULL, content TEXT DEFAULT NULL, html_content TEXT DEFAULT NULL, style_config JSON DEFAULT NULL, position_config JSON DEFAULT NULL, data_binding JSON DEFAULT NULL, conditional_display JSON DEFAULT NULL, is_active BOOLEAN NOT NULL, is_required BOOLEAN NOT NULL, sort_order INT NOT NULL, css_class VARCHAR(50) DEFAULT NULL, element_id VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC7FCF6E16A30F70 ON document_ui_components (ui_template_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC7FCF6EB03A8386 ON document_ui_components (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC7FCF6E896DBBDE ON document_ui_components (updated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_ui_components.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_ui_components.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_ui_templates (id SERIAL NOT NULL, document_type_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(500) NOT NULL, description TEXT DEFAULT NULL, html_template TEXT DEFAULT NULL, css_styles TEXT DEFAULT NULL, layout_configuration JSON DEFAULT NULL, page_settings JSON DEFAULT NULL, header_footer_config JSON DEFAULT NULL, component_styles JSON DEFAULT NULL, variables JSON DEFAULT NULL, orientation VARCHAR(50) NOT NULL, paper_size VARCHAR(50) NOT NULL, margins JSON DEFAULT NULL, margin_top NUMERIC(5, 1) DEFAULT NULL, margin_right NUMERIC(5, 1) DEFAULT NULL, margin_bottom NUMERIC(5, 1) DEFAULT NULL, margin_left NUMERIC(5, 1) DEFAULT NULL, show_page_numbers BOOLEAN NOT NULL, custom_css TEXT DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, is_global BOOLEAN NOT NULL, sort_order INT NOT NULL, usage_count INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_807E0DA2989D9B62 ON document_ui_templates (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_807E0DA261232A4F ON document_ui_templates (document_type_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_807E0DA2B03A8386 ON document_ui_templates (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_807E0DA2896DBBDE ON document_ui_templates (updated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_ui_templates.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_ui_templates.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_versions (id SERIAL NOT NULL, document_id INT NOT NULL, created_by_id INT DEFAULT NULL, version VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, content TEXT DEFAULT NULL, change_log TEXT DEFAULT NULL, is_current BOOLEAN NOT NULL, file_size BIGINT DEFAULT NULL, checksum VARCHAR(100) DEFAULT NULL, additional_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_961DB18BC33F7837 ON document_versions (document_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_961DB18BB03A8386 ON document_versions (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN document_versions.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE documents (id SERIAL NOT NULL, document_type_id INT NOT NULL, category_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(500) NOT NULL, description TEXT DEFAULT NULL, content TEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, is_active BOOLEAN NOT NULL, is_public BOOLEAN NOT NULL, version VARCHAR(50) DEFAULT NULL, tags JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, download_count INT DEFAULT 0 NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_A2B07288989D9B62 ON documents (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B0728861232A4F ON documents (document_type_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B0728812469DE2 ON documents (category_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B07288B03A8386 ON documents (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B07288896DBBDE ON documents (updated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN documents.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN documents.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN documents.published_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN documents.expires_at IS '(DC2Type:datetime_immutable)'
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
            CREATE TABLE ext_log_entries (id SERIAL NOT NULL, action VARCHAR(8) NOT NULL, logged_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, object_id VARCHAR(64) DEFAULT NULL, object_class VARCHAR(191) NOT NULL, version INT NOT NULL, data TEXT DEFAULT NULL, username VARCHAR(191) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX log_class_lookup_idx ON ext_log_entries (object_class)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX log_date_lookup_idx ON ext_log_entries (logged_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX log_user_lookup_idx ON ext_log_entries (username)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX log_version_lookup_idx ON ext_log_entries (object_id, object_class, version)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN ext_log_entries.data IS '(DC2Type:array)'
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
            CREATE TABLE mentors (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, position VARCHAR(150) NOT NULL, company_name VARCHAR(200) NOT NULL, company_siret VARCHAR(14) NOT NULL, expertise_domains JSON NOT NULL, experience_years INT NOT NULL, education_level VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, email_verified BOOLEAN NOT NULL, email_verification_token VARCHAR(100) DEFAULT NULL, email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, password_reset_token VARCHAR(100) DEFAULT NULL, password_reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_7AE525BAE7927C74 ON mentors (email)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN mentors.email_verified_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN mentors.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN mentors.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN mentors.last_login_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN mentors.password_reset_token_expires_at IS '(DC2Type:datetime_immutable)'
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
            CREATE TABLE needs_analysis_requests (id SERIAL NOT NULL, created_by_admin_id INT NOT NULL, formation_id INT DEFAULT NULL, prospect_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, token VARCHAR(36) NOT NULL, recipient_email VARCHAR(180) NOT NULL, recipient_name VARCHAR(255) NOT NULL, company_name VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, admin_notes TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_FCFD4E0C5F37A13B ON needs_analysis_requests (token)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FCFD4E0C64F1F4EE ON needs_analysis_requests (created_by_admin_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FCFD4E0C5200282E ON needs_analysis_requests (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FCFD4E0CD182060A ON needs_analysis_requests (prospect_id)
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
            CREATE TABLE prospect_notes (id SERIAL NOT NULL, prospect_id INT NOT NULL, created_by_id INT NOT NULL, title VARCHAR(200) NOT NULL, content TEXT NOT NULL, type VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, metadata JSON DEFAULT NULL, is_important BOOLEAN NOT NULL, is_private BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_40653D66D182060A ON prospect_notes (prospect_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_40653D66B03A8386 ON prospect_notes (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE prospects (id SERIAL NOT NULL, assigned_to_id INT DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) DEFAULT NULL, company VARCHAR(150) DEFAULT NULL, position VARCHAR(100) DEFAULT NULL, status VARCHAR(20) NOT NULL, priority VARCHAR(20) NOT NULL, source VARCHAR(50) DEFAULT NULL, description TEXT DEFAULT NULL, estimated_budget NUMERIC(10, 2) DEFAULT NULL, expected_closure_date DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_contact_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, next_follow_up_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, custom_fields JSON DEFAULT NULL, tags JSON DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_35730C06E7927C74 ON prospects (email)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_35730C06F4BD7827 ON prospects (assigned_to_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE prospect_formations (prospect_id INT NOT NULL, formation_id INT NOT NULL, PRIMARY KEY(prospect_id, formation_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FFB8A3F2D182060A ON prospect_formations (prospect_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FFB8A3F25200282E ON prospect_formations (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE prospect_services (prospect_id INT NOT NULL, service_id INT NOT NULL, PRIMARY KEY(prospect_id, service_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F24FBBB4D182060A ON prospect_services (prospect_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F24FBBB4ED5CA9E6 ON prospect_services (service_id)
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
            CREATE TABLE question (id SERIAL NOT NULL, questionnaire_id INT NOT NULL, question_text TEXT NOT NULL, type VARCHAR(50) NOT NULL, order_index INT NOT NULL, is_required BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, help_text TEXT DEFAULT NULL, placeholder TEXT DEFAULT NULL, min_length INT DEFAULT NULL, max_length INT DEFAULT NULL, validation_rules JSON DEFAULT NULL, allowed_file_types JSON DEFAULT NULL, max_file_size INT DEFAULT NULL, points INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B6F7494ECE07E8FF ON question (questionnaire_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN question.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN question.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE question_option (id SERIAL NOT NULL, question_id INT NOT NULL, option_text TEXT NOT NULL, order_index INT NOT NULL, is_correct BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, points INT DEFAULT NULL, explanation TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5DDB2FB81E27F6BF ON question_option (question_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN question_option.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN question_option.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE question_response (id SERIAL NOT NULL, question_id INT NOT NULL, questionnaire_response_id INT NOT NULL, text_response TEXT DEFAULT NULL, choice_response JSON DEFAULT NULL, file_response VARCHAR(255) DEFAULT NULL, number_response INT DEFAULT NULL, date_response DATE DEFAULT NULL, score_earned INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5D73BBF71E27F6BF ON question_response (question_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5D73BBF772D7F260 ON question_response (questionnaire_response_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN question_response.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN question_response.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE questionnaire (id SERIAL NOT NULL, formation_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(20) NOT NULL, is_multi_step BOOLEAN NOT NULL, questions_per_step INT NOT NULL, allow_back_navigation BOOLEAN NOT NULL, show_progress_bar BOOLEAN NOT NULL, require_all_questions BOOLEAN NOT NULL, time_limit_minutes INT DEFAULT NULL, welcome_message TEXT DEFAULT NULL, completion_message TEXT DEFAULT NULL, email_subject TEXT DEFAULT NULL, email_template TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_7A64DAF989D9B62 ON questionnaire (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7A64DAF5200282E ON questionnaire (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN questionnaire.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN questionnaire.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE questionnaire_response (id SERIAL NOT NULL, questionnaire_id INT NOT NULL, formation_id INT DEFAULT NULL, token VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) DEFAULT NULL, company VARCHAR(150) DEFAULT NULL, status VARCHAR(20) NOT NULL, current_step INT DEFAULT NULL, total_score INT DEFAULT NULL, max_possible_score INT DEFAULT NULL, score_percentage NUMERIC(5, 2) DEFAULT NULL, evaluation_status VARCHAR(20) NOT NULL, evaluator_notes TEXT DEFAULT NULL, recommendation TEXT DEFAULT NULL, duration_minutes INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, evaluated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_A04002765F37A13B ON questionnaire_response (token)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A0400276CE07E8FF ON questionnaire_response (questionnaire_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A04002765200282E ON questionnaire_response (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN questionnaire_response.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN questionnaire_response.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN questionnaire_response.started_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN questionnaire_response.completed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN questionnaire_response.evaluated_at IS '(DC2Type:datetime_immutable)'
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
            CREATE TABLE session_registrations (id SERIAL NOT NULL, session_id INT NOT NULL, prospect_id INT DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) DEFAULT NULL, company VARCHAR(150) DEFAULT NULL, position VARCHAR(100) DEFAULT NULL, status VARCHAR(50) NOT NULL, notes TEXT DEFAULT NULL, special_requirements TEXT DEFAULT NULL, additional_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, documents_delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, documents_acknowledged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, document_acknowledgment_token VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C9AF7FEC613FECDF ON session_registrations (session_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C9AF7FECD182060A ON session_registrations (prospect_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN session_registrations.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN session_registrations.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (id SERIAL NOT NULL, formation_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, registration_deadline DATE DEFAULT NULL, location VARCHAR(255) NOT NULL, address VARCHAR(500) DEFAULT NULL, max_capacity INT NOT NULL, min_capacity INT NOT NULL, current_registrations INT NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(50) NOT NULL, is_active BOOLEAN NOT NULL, instructor VARCHAR(100) DEFAULT NULL, notes TEXT DEFAULT NULL, additional_info JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9A609D135200282E ON sessions (formation_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN sessions.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN sessions.updated_at IS '(DC2Type:datetime_immutable)'
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
            CREATE TABLE teachers (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, birth_date DATE DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, specialty VARCHAR(100) DEFAULT NULL, title VARCHAR(100) DEFAULT NULL, biography TEXT DEFAULT NULL, qualifications VARCHAR(200) DEFAULT NULL, years_of_experience INT DEFAULT NULL, is_active BOOLEAN NOT NULL, email_verified BOOLEAN NOT NULL, email_verification_token VARCHAR(100) DEFAULT NULL, email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, password_reset_token VARCHAR(100) DEFAULT NULL, password_reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_ED071FF6E7927C74 ON teachers (email)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN teachers.email_verified_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN teachers.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN teachers.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN teachers.last_login_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN teachers.password_reset_token_expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD CONSTRAINT FK_9B5AB644CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance_records ADD CONSTRAINT FK_9B5AB644613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
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
            ALTER TABLE contact_requests ADD CONSTRAINT FK_E1A04AC6D182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE course ADD CONSTRAINT FK_169E6FB9579F4768 FOREIGN KEY (chapter_id) REFERENCES chapter (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_categories ADD CONSTRAINT FK_9B30ED3E727ACA70 FOREIGN KEY (parent_id) REFERENCES document_categories (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_metadata ADD CONSTRAINT FK_C0D5C54DC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates ADD CONSTRAINT FK_7D10552F61232A4F FOREIGN KEY (document_type_id) REFERENCES document_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates ADD CONSTRAINT FK_7D10552FB03A8386 FOREIGN KEY (created_by_id) REFERENCES admins (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates ADD CONSTRAINT FK_7D10552F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES admins (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components ADD CONSTRAINT FK_DC7FCF6E16A30F70 FOREIGN KEY (ui_template_id) REFERENCES document_ui_templates (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components ADD CONSTRAINT FK_DC7FCF6EB03A8386 FOREIGN KEY (created_by_id) REFERENCES admins (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components ADD CONSTRAINT FK_DC7FCF6E896DBBDE FOREIGN KEY (updated_by_id) REFERENCES admins (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD CONSTRAINT FK_807E0DA261232A4F FOREIGN KEY (document_type_id) REFERENCES document_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD CONSTRAINT FK_807E0DA2B03A8386 FOREIGN KEY (created_by_id) REFERENCES admins (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ADD CONSTRAINT FK_807E0DA2896DBBDE FOREIGN KEY (updated_by_id) REFERENCES admins (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_versions ADD CONSTRAINT FK_961DB18BC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_versions ADD CONSTRAINT FK_961DB18BB03A8386 FOREIGN KEY (created_by_id) REFERENCES admins (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B0728861232A4F FOREIGN KEY (document_type_id) REFERENCES document_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B0728812469DE2 FOREIGN KEY (category_id) REFERENCES document_categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288B03A8386 FOREIGN KEY (created_by_id) REFERENCES admins (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288896DBBDE FOREIGN KEY (updated_by_id) REFERENCES admins (id) NOT DEFERRABLE INITIALLY IMMEDIATE
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
            ALTER TABLE needs_analysis_requests ADD CONSTRAINT FK_FCFD4E0C64F1F4EE FOREIGN KEY (created_by_admin_id) REFERENCES admins (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests ADD CONSTRAINT FK_FCFD4E0C5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests ADD CONSTRAINT FK_FCFD4E0CD182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_notes ADD CONSTRAINT FK_40653D66D182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_notes ADD CONSTRAINT FK_40653D66B03A8386 FOREIGN KEY (created_by_id) REFERENCES admins (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospects ADD CONSTRAINT FK_35730C06F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES admins (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_formations ADD CONSTRAINT FK_FFB8A3F2D182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_formations ADD CONSTRAINT FK_FFB8A3F25200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_services ADD CONSTRAINT FK_F24FBBB4D182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_services ADD CONSTRAINT FK_F24FBBB4ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qcm ADD CONSTRAINT FK_D7A1FEF4591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE question ADD CONSTRAINT FK_B6F7494ECE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE question_option ADD CONSTRAINT FK_5DDB2FB81E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE question_response ADD CONSTRAINT FK_5D73BBF71E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE question_response ADD CONSTRAINT FK_5D73BBF772D7F260 FOREIGN KEY (questionnaire_response_id) REFERENCES questionnaire_response (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE questionnaire ADD CONSTRAINT FK_7A64DAF5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE questionnaire_response ADD CONSTRAINT FK_A0400276CE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE questionnaire_response ADD CONSTRAINT FK_A04002765200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2DEDCBB4E FOREIGN KEY (service_category_id) REFERENCES service_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations ADD CONSTRAINT FK_C9AF7FEC613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations ADD CONSTRAINT FK_C9AF7FECD182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions ADD CONSTRAINT FK_9A609D135200282E FOREIGN KEY (formation_id) REFERENCES formation (id) NOT DEFERRABLE INITIALLY IMMEDIATE
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
            ALTER TABLE contact_requests DROP CONSTRAINT FK_E1A04AC6D182060A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE course DROP CONSTRAINT FK_169E6FB9579F4768
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_categories DROP CONSTRAINT FK_9B30ED3E727ACA70
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_metadata DROP CONSTRAINT FK_C0D5C54DC33F7837
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates DROP CONSTRAINT FK_7D10552F61232A4F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates DROP CONSTRAINT FK_7D10552FB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_templates DROP CONSTRAINT FK_7D10552F896DBBDE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components DROP CONSTRAINT FK_DC7FCF6E16A30F70
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components DROP CONSTRAINT FK_DC7FCF6EB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_components DROP CONSTRAINT FK_DC7FCF6E896DBBDE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP CONSTRAINT FK_807E0DA261232A4F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP CONSTRAINT FK_807E0DA2B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates DROP CONSTRAINT FK_807E0DA2896DBBDE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_versions DROP CONSTRAINT FK_961DB18BC33F7837
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_versions DROP CONSTRAINT FK_961DB18BB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B0728861232A4F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B0728812469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B07288B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B07288896DBBDE
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
            ALTER TABLE needs_analysis_requests DROP CONSTRAINT FK_FCFD4E0C64F1F4EE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests DROP CONSTRAINT FK_FCFD4E0C5200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE needs_analysis_requests DROP CONSTRAINT FK_FCFD4E0CD182060A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_notes DROP CONSTRAINT FK_40653D66D182060A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_notes DROP CONSTRAINT FK_40653D66B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospects DROP CONSTRAINT FK_35730C06F4BD7827
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_formations DROP CONSTRAINT FK_FFB8A3F2D182060A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_formations DROP CONSTRAINT FK_FFB8A3F25200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_services DROP CONSTRAINT FK_F24FBBB4D182060A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_services DROP CONSTRAINT FK_F24FBBB4ED5CA9E6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qcm DROP CONSTRAINT FK_D7A1FEF4591CC992
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE question DROP CONSTRAINT FK_B6F7494ECE07E8FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE question_option DROP CONSTRAINT FK_5DDB2FB81E27F6BF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE question_response DROP CONSTRAINT FK_5D73BBF71E27F6BF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE question_response DROP CONSTRAINT FK_5D73BBF772D7F260
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE questionnaire DROP CONSTRAINT FK_7A64DAF5200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE questionnaire_response DROP CONSTRAINT FK_A0400276CE07E8FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE questionnaire_response DROP CONSTRAINT FK_A04002765200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service DROP CONSTRAINT FK_E19D9AD2DEDCBB4E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations DROP CONSTRAINT FK_C9AF7FEC613FECDF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations DROP CONSTRAINT FK_C9AF7FECD182060A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sessions DROP CONSTRAINT FK_9A609D135200282E
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
            DROP TABLE admins
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE attendance_records
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
            DROP TABLE document_categories
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_metadata
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_templates
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_types
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_ui_components
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_ui_templates
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_versions
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE documents
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE exercise
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE ext_log_entries
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE formation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE individual_needs_analyses
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mentors
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE module
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE needs_analysis_requests
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE prospect_notes
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE prospects
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE prospect_formations
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE prospect_services
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE qcm
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE question
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE question_option
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE question_response
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE questionnaire
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE questionnaire_response
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE service
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE service_category
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE session_registrations
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE sessions
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE student_progress
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE students
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE teachers
        SQL);
    }
}
