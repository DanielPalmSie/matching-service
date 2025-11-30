<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251130185917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE refresh_tokens ADD refresh_token VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE refresh_tokens ADD username VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE refresh_tokens ADD valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE refresh_tokens DROP refresh_token');
        $this->addSql('ALTER TABLE refresh_tokens DROP username');
        $this->addSql('ALTER TABLE refresh_tokens DROP valid');
    }
}
