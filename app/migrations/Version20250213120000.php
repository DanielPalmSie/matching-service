<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250213120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add magic login token table for passwordless authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE magic_login_token (id UUID NOT NULL, user_id INT NOT NULL, token VARCHAR(128) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_MAGIC_LOGIN_TOKEN_USER_ID ON magic_login_token (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MAGIC_LOGIN_TOKEN_TOKEN ON magic_login_token (token)');
        $this->addSql('ALTER TABLE magic_login_token ADD CONSTRAINT FK_MAGIC_LOGIN_TOKEN_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magic_login_token DROP CONSTRAINT FK_MAGIC_LOGIN_TOKEN_USER');
        $this->addSql('DROP TABLE magic_login_token');
    }
}
