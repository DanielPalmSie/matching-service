<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure unique chats per pair and origin context.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_chat_pair_origin ON chat (pair_key, origin_type, origin_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_chat_pair_no_origin ON chat (pair_key) WHERE origin_type IS NULL AND origin_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_chat_pair_origin');
        $this->addSql('DROP INDEX IF EXISTS uniq_chat_pair_no_origin');
    }
}
