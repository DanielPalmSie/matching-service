<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add telegram_chat_id to magic_login_token to link tokens with Telegram chats.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magic_login_token ADD telegram_chat_id BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magic_login_token DROP telegram_chat_id');
    }
}
