<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_feedback table for storing overall app feedback';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('app_feedback');
        $table->addColumn('id', 'bigint', ['autoincrement' => true]);
        $table->addColumn('user_id', 'bigint', ['notnull' => true]);
        $table->addColumn('rating', 'smallint', ['notnull' => true]);
        $table->addColumn('main_issue', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('comment', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['created_at'], 'idx_app_feedback_created_at');
        $table->addIndex(['main_issue'], 'idx_app_feedback_main_issue');
        $table->addIndex(['rating'], 'idx_app_feedback_rating');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('app_feedback');
    }
}
