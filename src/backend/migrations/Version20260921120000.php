<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the category table with a UUID primary key and an optional parent.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (id UUID NOT NULL, parent_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_64C19C1727ACA70 ON category (parent_id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE category');
    }
}
