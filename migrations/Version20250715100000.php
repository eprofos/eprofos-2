<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Module table and add modules relationship to Formation';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE module (id SERIAL PRIMARY KEY, formation_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, learning_objectives JSON DEFAULT NULL, prerequisites TEXT DEFAULT NULL, duration_hours INT NOT NULL, order_index INT NOT NULL, evaluation_methods TEXT DEFAULT NULL, teaching_methods TEXT DEFAULT NULL, resources JSON DEFAULT NULL, success_criteria JSON DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL)');
        $this->addSql('CREATE INDEX IDX_C2426285C266B5E ON module (formation_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C242628989D9B62 ON module (slug)');
        $this->addSql('COMMENT ON COLUMN module.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN module.updated_at IS \'(DC2Type:datetime_immutable)\'');
        
        $this->addSql('CREATE TABLE chapter (id SERIAL PRIMARY KEY, module_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, duration_minutes INT NOT NULL, order_index INT NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL)');
        $this->addSql('CREATE INDEX IDX_F981B52EAFC2B591 ON chapter (module_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F981B52E989D9B62 ON chapter (slug)');
        $this->addSql('COMMENT ON COLUMN chapter.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN chapter.updated_at IS \'(DC2Type:datetime_immutable)\'');
        
        $this->addSql('ALTER TABLE module ADD CONSTRAINT FK_C2426285C266B5E FOREIGN KEY (formation_id) REFERENCES formation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52EAFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT FK_F981B52EAFC2B591');
        $this->addSql('ALTER TABLE module DROP CONSTRAINT FK_C2426285C266B5E');
        $this->addSql('DROP TABLE chapter');
        $this->addSql('DROP TABLE module');
    }
}
