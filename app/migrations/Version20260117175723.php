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
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('chat');

        if (!isset($columns['pair_key'])) {
            $this->addSql('ALTER TABLE chat ADD pair_key VARCHAR(128) NOT NULL');
        }

        if (!isset($columns['origin_type'])) {
            $this->addSql('ALTER TABLE chat ADD origin_type VARCHAR(32) DEFAULT NULL');
        }

        if (!isset($columns['origin_id'])) {
            $this->addSql('ALTER TABLE chat ADD origin_id INT DEFAULT NULL');
        }
    }


    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('chat');

        if (isset($columns['pair_key'])) {
            $this->addSql('ALTER TABLE chat DROP pair_key');
        }

        if (isset($columns['origin_type'])) {
            $this->addSql('ALTER TABLE chat DROP origin_type');
        }

        if (isset($columns['origin_id'])) {
            $this->addSql('ALTER TABLE chat DROP origin_id');
        }
    }

}
