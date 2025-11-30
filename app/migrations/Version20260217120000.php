<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add latitude and longitude fields to user and request tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD COLUMN lat DOUBLE PRECISION');
        $this->addSql('ALTER TABLE "user" ADD COLUMN lng DOUBLE PRECISION');
        $this->addSql('ALTER TABLE request ADD COLUMN lat DOUBLE PRECISION');
        $this->addSql('ALTER TABLE request ADD COLUMN lng DOUBLE PRECISION');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE request DROP COLUMN lat');
        $this->addSql('ALTER TABLE request DROP COLUMN lng');
        $this->addSql('ALTER TABLE "user" DROP COLUMN lat');
        $this->addSql('ALTER TABLE "user" DROP COLUMN lng');
    }
}
