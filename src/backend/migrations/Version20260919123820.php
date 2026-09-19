<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919123820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the product table with a UUID primary key and unique SKU.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product (id UUID NOT NULL, sku VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_sku ON product (sku)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product');
    }
}
