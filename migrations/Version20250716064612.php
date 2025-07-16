<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250716064612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
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
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
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
    }
}
