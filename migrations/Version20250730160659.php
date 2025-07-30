<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730160659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations ADD linked_student_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations ADD linked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN session_registrations.linked_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations ADD CONSTRAINT FK_C9AF7FEC5C50945D FOREIGN KEY (linked_student_id) REFERENCES students (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C9AF7FEC5C50945D ON session_registrations (linked_student_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations DROP CONSTRAINT FK_C9AF7FEC5C50945D
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_C9AF7FEC5C50945D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations DROP linked_student_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session_registrations DROP linked_at
        SQL);
    }
}
