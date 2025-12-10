<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand user_embeddings to 3072 dimensions and drop IVFFLAT index (not supported for >2000 dimensions).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS user_embeddings_embedding_idx');
        $this->addSql('DROP INDEX IF EXISTS user_embeddings_cosine_idx');

        $this->addSql('ALTER TABLE user_embeddings ALTER COLUMN embedding TYPE vector(3072)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS user_embeddings_embedding_idx');

        $this->addSql('ALTER TABLE user_embeddings ALTER COLUMN embedding TYPE vector(1536)');

        $this->addSql('CREATE INDEX IF NOT EXISTS user_embeddings_cosine_idx ON user_embeddings USING ivfflat (embedding vector_cosine_ops)');
    }
}
