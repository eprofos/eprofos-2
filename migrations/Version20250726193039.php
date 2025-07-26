<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726193039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD contract_number VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD company_contact_person VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD company_contact_email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD company_contact_phone VARCHAR(20) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD objectives TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD tasks TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD evaluation_criteria TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts ADD compensation INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_403DB586AAD0FA19 ON alternance_contracts (contract_number)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_403DB586AAD0FA19
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP contract_number
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP company_contact_person
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP company_contact_email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP company_contact_phone
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP objectives
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP tasks
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP evaluation_criteria
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alternance_contracts DROP compensation
        SQL);
    }
}
