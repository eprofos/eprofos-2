<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to optimize audit log (ext_log_entries) table performance
 */
final class Version20250720180132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes to ext_log_entries table for better audit log performance';
    }

    public function up(Schema $schema): void
    {
        // Add indexes for common audit log queries
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_log_class_lookup ON ext_log_entries (object_class, object_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_log_date ON ext_log_entries (logged_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_log_user ON ext_log_entries (username)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_log_action ON ext_log_entries (action)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_log_version ON ext_log_entries (object_class, object_id, version)');
    }

    public function down(Schema $schema): void
    {
        // Remove the indexes
        $this->addSql('DROP INDEX IF EXISTS idx_log_class_lookup');
        $this->addSql('DROP INDEX IF EXISTS idx_log_date');
        $this->addSql('DROP INDEX IF EXISTS idx_log_user');
        $this->addSql('DROP INDEX IF EXISTS idx_log_action');
        $this->addSql('DROP INDEX IF EXISTS idx_log_version');
    }
}
