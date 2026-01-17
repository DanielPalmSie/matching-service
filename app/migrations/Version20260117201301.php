<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117201301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat ADD context_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE chat ADD context_subtitle VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE chat ADD context_source VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat DROP context_title');
        $this->addSql('ALTER TABLE chat DROP context_subtitle');
        $this->addSql('ALTER TABLE chat DROP context_source');
    }
}
