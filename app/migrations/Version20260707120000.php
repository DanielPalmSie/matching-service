<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email, password and roles fields required for JWT authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD roles JSON NOT NULL DEFAULT \'["ROLE_USER"]\'');
        $this->addSql('ALTER TABLE "user" ADD password VARCHAR(255) DEFAULT NULL');

        $this->addSql('UPDATE "user" SET email = external_id WHERE email IS NULL');

        $this->addSql(
            'UPDATE "user" SET password = :password WHERE password IS NULL',
            [
                'password' => '$2y$12$9RCE7YJF7uXI2H7nuQjReuRPq2CunbhB1zZ4ywcUibotF5fQ/t9lK',
            ]
        );

        $this->addSql('ALTER TABLE "user" ALTER COLUMN email SET NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER COLUMN password SET NOT NULL');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74');
        $this->addSql('ALTER TABLE "user" DROP email');
        $this->addSql('ALTER TABLE "user" DROP roles');
        $this->addSql('ALTER TABLE "user" DROP password');
    }
}
