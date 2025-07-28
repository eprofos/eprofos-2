<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250728205425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert decimal fields to float for proper type mapping compliance';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ALTER margin_top TYPE DOUBLE PRECISION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ALTER margin_right TYPE DOUBLE PRECISION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ALTER margin_bottom TYPE DOUBLE PRECISION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ALTER margin_left TYPE DOUBLE PRECISION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mission_assignments ALTER completion_rate TYPE DOUBLE PRECISION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ALTER completion_percentage TYPE DOUBLE PRECISION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ALTER attendance_rate TYPE DOUBLE PRECISION
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ALTER margin_top TYPE NUMERIC(5, 1)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ALTER margin_right TYPE NUMERIC(5, 1)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ALTER margin_bottom TYPE NUMERIC(5, 1)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_ui_templates ALTER margin_left TYPE NUMERIC(5, 1)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ALTER completion_percentage TYPE NUMERIC(5, 2)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ALTER attendance_rate TYPE NUMERIC(5, 2)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mission_assignments ALTER completion_rate TYPE NUMERIC(5, 2)
        SQL);
    }
}
