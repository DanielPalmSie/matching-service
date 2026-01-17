<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add chat origin context and pair keys for request-based chats.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat ADD origin_type VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE chat ADD origin_id INT DEFAULT NULL');
        $this->addSql("ALTER TABLE chat ADD pair_key VARCHAR(128) DEFAULT '' NOT NULL");
        $this->addSql('CREATE INDEX IDX_CHAT_ORIGIN_TYPE_ID ON chat (origin_type, origin_id)');
        $this->addSql('CREATE INDEX IDX_CHAT_PAIR_KEY ON chat (pair_key)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CHAT_PAIR_ORIGIN ON chat (pair_key, origin_type, origin_id)');
        $this->addSql("UPDATE chat c SET pair_key = (p.min_user_id || ':' || p.max_user_id)
            FROM (
                SELECT chat_id, MIN(user_id) AS min_user_id, MAX(user_id) AS max_user_id
                FROM chat_participants
                GROUP BY chat_id
            ) p
            WHERE c.id = p.chat_id");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CHAT_PAIR_ORIGIN');
        $this->addSql('DROP INDEX IDX_CHAT_PAIR_KEY');
        $this->addSql('DROP INDEX IDX_CHAT_ORIGIN_TYPE_ID');
        $this->addSql('ALTER TABLE chat DROP origin_type');
        $this->addSql('ALTER TABLE chat DROP origin_id');
        $this->addSql('ALTER TABLE chat DROP pair_key');
    }
}
