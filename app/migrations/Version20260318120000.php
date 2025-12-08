<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email confirmation tokens and verification flags to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_confirmation_token (id UUID NOT NULL, user_id INT NOT NULL, token VARCHAR(128) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EMAIL_CONFIRMATION_TOKEN_USER_ID ON email_confirmation_token (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EMAIL_CONFIRMATION_TOKEN_TOKEN ON email_confirmation_token (token)');
        $this->addSql('ALTER TABLE email_confirmation_token ADD CONSTRAINT FK_EMAIL_CONFIRMATION_TOKEN_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD is_verified BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_confirmation_token DROP CONSTRAINT FK_EMAIL_CONFIRMATION_TOKEN_USER');
        $this->addSql('DROP TABLE email_confirmation_token');
        $this->addSql('ALTER TABLE "user" DROP is_verified');
        $this->addSql('ALTER TABLE "user" DROP email_verified_at');
    }
}
