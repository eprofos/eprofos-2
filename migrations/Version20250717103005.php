<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add prospect relationships to SessionRegistration, ContactRequest, and NeedsAnalysisRequest
 */
final class Version20250717103005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add prospect relationships to existing entities for unified customer contact management';
    }

    public function up(Schema $schema): void
    {
        // Add prospect_id column to session_registrations
        $this->addSql('ALTER TABLE session_registrations ADD prospect_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE session_registrations ADD CONSTRAINT FK_C9AF7FECD182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C9AF7FECD182060A ON session_registrations (prospect_id)');

        // Add prospect_id column to contact_requests
        $this->addSql('ALTER TABLE contact_requests ADD prospect_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_requests ADD CONSTRAINT FK_C9AF7FED182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C9AF7FED182060A ON contact_requests (prospect_id)');

        // Add prospect_id column to needs_analysis_requests
        $this->addSql('ALTER TABLE needs_analysis_requests ADD prospect_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE needs_analysis_requests ADD CONSTRAINT FK_C9AF7FEE182060A FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C9AF7FEE182060A ON needs_analysis_requests (prospect_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign keys and columns
        $this->addSql('ALTER TABLE session_registrations DROP CONSTRAINT FK_C9AF7FECD182060A');
        $this->addSql('DROP INDEX IDX_C9AF7FECD182060A');
        $this->addSql('ALTER TABLE session_registrations DROP prospect_id');

        $this->addSql('ALTER TABLE contact_requests DROP CONSTRAINT FK_C9AF7FED182060A');
        $this->addSql('DROP INDEX IDX_C9AF7FED182060A');
        $this->addSql('ALTER TABLE contact_requests DROP prospect_id');

        $this->addSql('ALTER TABLE needs_analysis_requests DROP CONSTRAINT FK_C9AF7FEE182060A');
        $this->addSql('DROP INDEX IDX_C9AF7FEE182060A');
        $this->addSql('ALTER TABLE needs_analysis_requests DROP prospect_id');
    }
}
