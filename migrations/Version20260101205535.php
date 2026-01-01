<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101205535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create refresh_token table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_token (
              id SERIAL NOT NULL,
              refresh_token VARCHAR(128) NOT NULL,
              username VARCHAR(255) NOT NULL,
              valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_REFRESH_TOKEN ON refresh_token (refresh_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_token');
    }
}
