<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731045604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise ADD time_limit_minutes INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise ADD content TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise ADD max_attempts INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise ADD is_auto_graded BOOLEAN NOT NULL DEFAULT false
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise ADD resource_files JSON DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise DROP time_limit_minutes
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise DROP content
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise DROP max_attempts
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise DROP is_auto_graded
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise DROP resource_files
        SQL);
    }
}
