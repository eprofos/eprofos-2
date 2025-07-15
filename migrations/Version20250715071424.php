<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715071424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add structured objectives fields for Qualiopi 2.5 compliance';
    }

    public function up(Schema $schema): void
    {
        // Add new JSON columns for structured objectives (Qualiopi 2.5 compliance)
        $this->addSql('ALTER TABLE formation ADD operational_objectives JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE formation ADD evaluable_objectives JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE formation ADD evaluation_criteria JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE formation ADD success_indicators JSON DEFAULT NULL');
        
        // Add comments for PostgreSQL
        $this->addSql('COMMENT ON COLUMN formation.operational_objectives IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN formation.evaluable_objectives IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN formation.evaluation_criteria IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN formation.success_indicators IS \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove the structured objectives columns
        $this->addSql('ALTER TABLE formation DROP COLUMN operational_objectives');
        $this->addSql('ALTER TABLE formation DROP COLUMN evaluable_objectives');
        $this->addSql('ALTER TABLE formation DROP COLUMN evaluation_criteria');
        $this->addSql('ALTER TABLE formation DROP COLUMN success_indicators');
    }
}
