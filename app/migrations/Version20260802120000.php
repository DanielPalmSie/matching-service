<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add telegram_identity table for persistent Telegram mappings.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE telegram_identity (id SERIAL NOT NULL, user_id INT NOT NULL, telegram_chat_id VARCHAR(64) DEFAULT NULL, telegram_user_id VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7D72C539A76ED395 ON telegram_identity (user_id)');
        $this->addSql('ALTER TABLE telegram_identity ADD CONSTRAINT FK_7D72C539A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE telegram_identity');
    }
}
