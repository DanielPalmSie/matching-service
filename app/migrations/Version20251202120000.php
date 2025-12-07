<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251202120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable pgvector, move embeddings to user_embeddings table, and drop legacy JSON embeddings from requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql('CREATE TABLE user_embeddings (user_id INT NOT NULL, embedding VECTOR(1536) NOT NULL, PRIMARY KEY(user_id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS user_embeddings_cosine_idx ON user_embeddings USING ivfflat (embedding vector_cosine_ops)');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE request ADD embedding JSON DEFAULT NULL');
        $this->addSql('DROP INDEX IF EXISTS user_embeddings_cosine_idx');
        $this->addSql('DROP TABLE IF EXISTS user_embeddings');
        $this->addSql('DROP EXTENSION IF EXISTS vector');
    }
}
