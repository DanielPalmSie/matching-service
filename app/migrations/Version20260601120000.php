<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand user_embeddings to 3072 dimensions and rebuild IVFFLAT index with L2 distance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS user_embeddings_embedding_idx');
        $this->addSql('DROP INDEX IF EXISTS user_embeddings_cosine_idx');
        $this->addSql('ALTER TABLE user_embeddings ALTER COLUMN embedding TYPE vector(3072)');
        $this->addSql('CREATE INDEX user_embeddings_embedding_idx ON user_embeddings USING ivfflat (embedding vector_l2_ops) WITH (lists = 100)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS user_embeddings_embedding_idx');
        $this->addSql('ALTER TABLE user_embeddings ALTER COLUMN embedding TYPE vector(1536)');
        $this->addSql('CREATE INDEX IF NOT EXISTS user_embeddings_cosine_idx ON user_embeddings USING ivfflat (embedding vector_cosine_ops)');
    }
}
