<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store embeddings directly on requests with pgvector metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql('ALTER TABLE request ADD embedding VECTOR(3072) DEFAULT NULL');
        $this->addSql('ALTER TABLE request ADD embedding_model VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE request ADD embedding_status VARCHAR(16) NOT NULL DEFAULT \'ready\'');
        $this->addSql('ALTER TABLE request ADD embedding_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE request ADD embedding_error TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS request_embedding_hnsw_idx ON request USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS request_embedding_hnsw_idx');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding_error');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding_updated_at');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding_status');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding_model');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding');
    }
}
