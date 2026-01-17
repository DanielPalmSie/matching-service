<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add context fields to chat table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat ADD context_title VARCHAR(255) DEFAULT NULL, ADD context_subtitle VARCHAR(255) DEFAULT NULL, ADD context_source VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat DROP context_title, DROP context_subtitle, DROP context_source');
    }
}
