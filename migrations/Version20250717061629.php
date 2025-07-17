<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250717061629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
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
            ALTER TABLE prospect_notes ADD CONSTRAINT FK_40653D66D182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospect_notes ADD CONSTRAINT FK_40653D66B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prospects ADD CONSTRAINT FK_35730C06F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
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
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
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
    }
}
