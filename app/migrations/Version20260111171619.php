<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260111171619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-request embeddings to request table; keep legacy tables; rename some indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX uniq_email_confirmation_token_token RENAME TO "UNIQ_C50AEE9B5F37A13B"');
        $this->addSql('ALTER INDEX idx_email_confirmation_token_user_id RENAME TO "IDX_C50AEE9BA76ED395"');
        $this->addSql('ALTER INDEX uniq_magic_login_token_token RENAME TO "UNIQ_B3F87895F37A13B"');
        $this->addSql('ALTER INDEX idx_magic_login_token_user_id RENAME TO "IDX_B3F8789A76ED395"');

        $this->addSql('ALTER TABLE request ADD COLUMN embedding vector(3072)');
        $this->addSql('ALTER TABLE request ADD COLUMN embedding_model VARCHAR(64) DEFAULT NULL');


        $this->addSql('ALTER TABLE request ADD COLUMN embedding_status VARCHAR(16) NOT NULL DEFAULT \'pending\'');

        $this->addSql('ALTER TABLE request ADD COLUMN embedding_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE request ADD COLUMN embedding_error TEXT DEFAULT NULL');


    }

    public function down(Schema $schema): void
    {

        $this->addSql('ALTER INDEX "IDX_C50AEE9BA76ED395" RENAME TO idx_email_confirmation_token_user_id');
        $this->addSql('ALTER INDEX "UNIQ_C50AEE9B5F37A13B" RENAME TO uniq_email_confirmation_token_token');
        $this->addSql('ALTER INDEX "IDX_B3F8789A76ED395" RENAME TO idx_magic_login_token_user_id');
        $this->addSql('ALTER INDEX "UNIQ_B3F87895F37A13B" RENAME TO uniq_magic_login_token_token');

        $this->addSql('ALTER TABLE request DROP COLUMN embedding');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding_model');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding_status');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding_updated_at');
        $this->addSql('ALTER TABLE request DROP COLUMN embedding_error');

    }
}
