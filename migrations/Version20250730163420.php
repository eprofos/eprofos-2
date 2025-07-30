<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730163420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enhanced progress tracking fields to StudentProgress entity for Issue #67';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD course_progress JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD exercise_progress JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD qcm_progress JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD time_spent_tracking JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD learning_path JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD milestones JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD streak_days INT NOT NULL DEFAULT 0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress ADD last_milestone TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP course_progress
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP exercise_progress
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP qcm_progress
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP time_spent_tracking
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP learning_path
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP milestones
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP streak_days
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE student_progress DROP last_milestone
        SQL);
    }
}
