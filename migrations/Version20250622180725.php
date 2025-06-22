<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250622180725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD target_audience TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD access_modalities TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD handicap_accessibility TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD teaching_methods TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD evaluation_methods TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD contact_info TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD training_location TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD funding_modalities TEXT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP target_audience
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP access_modalities
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP handicap_accessibility
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP teaching_methods
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP evaluation_methods
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP contact_info
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP training_location
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP funding_modalities
        SQL);
    }
}
