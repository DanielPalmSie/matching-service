<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create match_feedback table for storing weekly feedback reports';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('match_feedback');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('comment', 'text', ['notnull' => false]);
        $table->addColumn('reason_code', 'string', ['length' => 32, 'notnull' => false]);
        $table->addColumn('relevance_score', 'smallint', ['notnull' => true]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['created_at'], 'idx_match_feedback_created_at');
        $table->addIndex(['reason_code'], 'idx_match_feedback_reason_code');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('match_feedback');
    }
}
