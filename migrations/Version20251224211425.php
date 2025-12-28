<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251224211425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create store table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE store (id UUID NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE store');
    }
}
