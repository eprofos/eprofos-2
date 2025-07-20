<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to create the ext_log_entries table for Gedmo Loggable extension
 */
final class Version20250720171912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ext_log_entries table for Gedmo Loggable extension to track changes in training entities';
    }

    public function up(Schema $schema): void
    {
        // Create the log entries table for Gedmo Loggable extension
        $this->addSql('CREATE TABLE ext_log_entries (
            id SERIAL PRIMARY KEY,
            action VARCHAR(8) NOT NULL,
            logged_at TIMESTAMP NOT NULL,
            object_id VARCHAR(64) DEFAULT NULL,
            object_class VARCHAR(191) NOT NULL,
            version INTEGER NOT NULL,
            data TEXT DEFAULT NULL,
            username VARCHAR(191) DEFAULT NULL
        )');
        
        // Create indexes for better performance
        $this->addSql('CREATE INDEX log_class_lookup_idx ON ext_log_entries (object_class)');
        $this->addSql('CREATE INDEX log_date_lookup_idx ON ext_log_entries (logged_at)');
        $this->addSql('CREATE INDEX log_user_lookup_idx ON ext_log_entries (username)');
        $this->addSql('CREATE INDEX log_version_lookup_idx ON ext_log_entries (object_id, object_class, version)');
    }

    public function down(Schema $schema): void
    {
        // Drop the log entries table
        $this->addSql('DROP TABLE ext_log_entries');
    }
}
