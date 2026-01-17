<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117175723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat ADD pair_key VARCHAR(128) NOT NULL');
        $this->addSql('ALTER TABLE chat ADD origin_type VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE chat ADD origin_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat DROP pair_key');
        $this->addSql('ALTER TABLE chat DROP origin_type');
        $this->addSql('ALTER TABLE chat DROP origin_id');
    }
}
