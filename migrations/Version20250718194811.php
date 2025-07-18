<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add indexes for duration fields to optimize duration calculation queries
 */
final class Version20250718194811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for duration fields to optimize duration calculation queries';
    }

    public function up(Schema $schema): void
    {
        // Add indexes for duration fields
        $this->addSql('CREATE INDEX IDX_FORMATION_DURATION_ACTIVE ON formation (duration_hours, is_active)');
        $this->addSql('CREATE INDEX IDX_MODULE_DURATION_ACTIVE ON module (duration_hours, is_active)');
        $this->addSql('CREATE INDEX IDX_CHAPTER_DURATION_ACTIVE ON chapter (duration_minutes, is_active)');
        $this->addSql('CREATE INDEX IDX_COURSE_DURATION_ACTIVE ON course (duration_minutes, is_active)');
        $this->addSql('CREATE INDEX IDX_EXERCISE_DURATION_ACTIVE ON exercise (estimated_duration_minutes, is_active)');
        $this->addSql('CREATE INDEX IDX_QCM_TIMELIMIT_ACTIVE ON qcm (time_limit_minutes, is_active)');
        
        // Add indexes for relationship queries used in duration calculation
        $this->addSql('CREATE INDEX IDX_COURSE_CHAPTER_ACTIVE ON course (chapter_id, is_active)');
        $this->addSql('CREATE INDEX IDX_CHAPTER_MODULE_ACTIVE ON chapter (module_id, is_active)');
        $this->addSql('CREATE INDEX IDX_MODULE_FORMATION_ACTIVE ON module (formation_id, is_active)');
        $this->addSql('CREATE INDEX IDX_EXERCISE_COURSE_ACTIVE ON exercise (course_id, is_active)');
        $this->addSql('CREATE INDEX IDX_QCM_COURSE_ACTIVE ON qcm (course_id, is_active)');
        
        // Add indexes for order-based queries
        $this->addSql('CREATE INDEX IDX_COURSE_ORDER ON course (chapter_id, order_index)');
        $this->addSql('CREATE INDEX IDX_CHAPTER_ORDER ON chapter (module_id, order_index)');
        $this->addSql('CREATE INDEX IDX_MODULE_ORDER ON module (formation_id, order_index)');
        $this->addSql('CREATE INDEX IDX_EXERCISE_ORDER ON exercise (course_id, order_index)');
        $this->addSql('CREATE INDEX IDX_QCM_ORDER ON qcm (course_id, order_index)');
    }

    public function down(Schema $schema): void
    {
        // Remove duration field indexes
        $this->addSql('DROP INDEX IDX_FORMATION_DURATION_ACTIVE');
        $this->addSql('DROP INDEX IDX_MODULE_DURATION_ACTIVE');
        $this->addSql('DROP INDEX IDX_CHAPTER_DURATION_ACTIVE');
        $this->addSql('DROP INDEX IDX_COURSE_DURATION_ACTIVE');
        $this->addSql('DROP INDEX IDX_EXERCISE_DURATION_ACTIVE');
        $this->addSql('DROP INDEX IDX_QCM_TIMELIMIT_ACTIVE');
        
        // Remove relationship indexes
        $this->addSql('DROP INDEX IDX_COURSE_CHAPTER_ACTIVE');
        $this->addSql('DROP INDEX IDX_CHAPTER_MODULE_ACTIVE');
        $this->addSql('DROP INDEX IDX_MODULE_FORMATION_ACTIVE');
        $this->addSql('DROP INDEX IDX_EXERCISE_COURSE_ACTIVE');
        $this->addSql('DROP INDEX IDX_QCM_COURSE_ACTIVE');
        
        // Remove order indexes
        $this->addSql('DROP INDEX IDX_COURSE_ORDER');
        $this->addSql('DROP INDEX IDX_CHAPTER_ORDER');
        $this->addSql('DROP INDEX IDX_MODULE_ORDER');
        $this->addSql('DROP INDEX IDX_EXERCISE_ORDER');
        $this->addSql('DROP INDEX IDX_QCM_ORDER');
    }
}
