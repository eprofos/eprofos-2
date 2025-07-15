<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715092755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove program column from formation table as it is now dynamically generated from modules and chapters';
    }

    public function up(Schema $schema): void
    {
        // Remove the program column as it's now dynamically generated
        $this->addSql('ALTER TABLE formation DROP COLUMN program');
    }

    public function down(Schema $schema): void
    {
        // Add back the program column for rollback
        $this->addSql('ALTER TABLE formation ADD COLUMN program TEXT DEFAULT NULL');
    }
}
