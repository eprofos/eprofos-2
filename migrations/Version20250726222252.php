<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726222252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE alternance_calendars (id SERIAL NOT NULL, student_id INT NOT NULL, contract_id INT NOT NULL, week SMALLINT NOT NULL, year SMALLINT NOT NULL, location VARCHAR(20) NOT NULL, center_sessions JSON DEFAULT NULL, company_activities JSON DEFAULT NULL, evaluations JSON DEFAULT NULL, meetings JSON DEFAULT NULL, holidays JSON DEFAULT NULL, notes TEXT DEFAULT NULL, is_confirmed BOOLEAN NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_by VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_CDB29EFACB944F1A ON alternance_calendars (student_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_CDB29EFA2576E0FD ON alternance_calendars (contract_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_week_year ON alternance_calendars (week, year)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_location ON alternance_calendars (location)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_confirmed ON alternance_calendars (is_confirmed)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX unique_student_week_year ON alternance_calendars (student_id, week, year)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_calendars ADD CONSTRAINT FK_CDB29EFACB944F1A FOREIGN KEY (student_id) REFERENCES students (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_calendars ADD CONSTRAINT FK_CDB29EFA2576E0FD FOREIGN KEY (contract_id) REFERENCES alternance_contracts (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_calendars DROP CONSTRAINT FK_CDB29EFACB944F1A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_calendars DROP CONSTRAINT FK_CDB29EFA2576E0FD
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE alternance_calendars
        SQL);
    }
}
