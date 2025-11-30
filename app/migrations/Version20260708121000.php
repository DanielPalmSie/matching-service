<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add match_id, target_request_id and main_issue to match_feedback table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('match_feedback');
        $table->addColumn('match_id', 'integer', ['notnull' => false]);
        $table->addColumn('target_request_id', 'integer', ['notnull' => false]);
        $table->addColumn('main_issue', 'string', ['length' => 50, 'notnull' => false]);
        $table->addIndex(['main_issue'], 'idx_match_feedback_main_issue');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('match_feedback');
        if ($table->hasIndex('idx_match_feedback_main_issue')) {
            $table->dropIndex('idx_match_feedback_main_issue');
        }
        $table->dropColumn('main_issue');
        $table->dropColumn('target_request_id');
        $table->dropColumn('match_id');
    }
}
