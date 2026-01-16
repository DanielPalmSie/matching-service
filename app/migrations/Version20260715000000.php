<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove type column from request table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE request DROP COLUMN type');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE request ADD type VARCHAR(50) DEFAULT 'unknown' NOT NULL");
    }
}
