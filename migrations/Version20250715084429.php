<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715084429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter ADD learning_objectives JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter ADD content_outline TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter ADD prerequisites TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter ADD learning_outcomes JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter ADD teaching_methods TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter ADD resources JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter ADD assessment_methods TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter ADD success_criteria JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_c2426285c266b5e RENAME TO IDX_C2426285200282E
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter DROP learning_objectives
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter DROP content_outline
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter DROP prerequisites
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter DROP learning_outcomes
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter DROP teaching_methods
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter DROP resources
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter DROP assessment_methods
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE chapter DROP success_criteria
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_c2426285200282e RENAME TO idx_c2426285c266b5e
        SQL);
    }
}
