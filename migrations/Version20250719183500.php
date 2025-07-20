<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add download_count column to documents table
 */
final class Version20250719183500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add download_count column to documents table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents ADD download_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents DROP download_count');
    }
}
