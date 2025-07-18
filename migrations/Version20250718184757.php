<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250718184757 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status field to legal_documents table with proper status management (draft, published, archived)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE legal_documents ADD status VARCHAR(20) NOT NULL DEFAULT 'draft'
        SQL);
        
        // Set status based on existing publishedAt field
        $this->addSql(<<<'SQL'
            UPDATE legal_documents 
            SET status = 'published' 
            WHERE published_at IS NOT NULL AND published_at <= NOW() AND is_active = true
        SQL);
        
        // Remove default value after updating existing records
        $this->addSql(<<<'SQL'
            ALTER TABLE legal_documents ALTER COLUMN status DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE legal_documents DROP status
        SQL);
    }
}
